<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Concerns\ManagesTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\User;
use App\Services\Security\TwoFactorChallenge;
use App\Support\SecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Connexion des superadmins : garde « platform », domaine central uniquement. */
class PlatformAuthController extends Controller
{
    use ManagesTwoFactor;

    public function login(Request $request, TwoFactorChallenge $challenges): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $admin = PlatformAdmin::where('email', strtolower($data['login']))->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        if ($admin->hasTwoFactorEnabled()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => $challenges->create(['type' => 'platform', 'admin_id' => $admin->id, 'remember' => (bool) ($data['remember'] ?? false)]),
                'message' => 'Saisissez le code de votre application d’authentification.',
            ]);
        }

        return $this->startSession($request, $admin, (bool) ($data['remember'] ?? false));
    }

    /** POST /platform/auth/two-factor { challenge, code | recovery_code } */
    public function twoFactor(Request $request, TwoFactorChallenge $challenges): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $challenge = $challenges->find($data['challenge']);
        $admin = ($challenge['type'] ?? null) === 'platform' ? PlatformAdmin::find($challenge['admin_id']) : null;

        if (! $admin) {
            throw ValidationException::withMessages(['code' => 'Session de connexion expirée. Saisissez à nouveau votre mot de passe.']);
        }

        if (! $admin->passesTwoFactorChallenge($data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $challenges->fail($data['challenge']);
            throw ValidationException::withMessages(['code' => 'Code incorrect.']);
        }

        $challenges->forget($data['challenge']);

        if (filled($data['recovery_code'] ?? null)) {
            Log::warning('Superadmin connecté avec un code de secours', ['admin' => $admin->email]);
        }

        return $this->startSession($request, $admin, (bool) $challenge['remember']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user('platform'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** PUT /platform/auth/password { current, password, password_confirmation } */
    public function password(Request $request): JsonResponse
    {
        $admin = $request->user('platform');
        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10), 'different:current'],
        ], ['password.different' => 'Choisissez un mot de passe différent du mot de passe actuel.']);

        if (! Hash::check($data['current'], $admin->password)) {
            throw ValidationException::withMessages(['current' => 'Mot de passe actuel incorrect.']);
        }

        $admin->forceFill(['password' => $data['password']])->save();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    protected function startSession(Request $request, PlatformAdmin $admin, bool $remember): JsonResponse
    {
        Auth::guard('platform')->login($admin, $remember);
        $request->session()->regenerate();
        $request->session()->forget('tenant_id');

        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => $this->present($admin)]);
    }

    protected function present(PlatformAdmin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'superadmin',
            'roles' => ['superadmin'],
            'permissions' => ['*'],
            'tenant' => null,
            'security' => SecurityPolicy::summary($admin),
        ];
    }

    protected function twoFactorUser(Request $request): User|PlatformAdmin
    {
        return $request->user('platform');
    }

    protected function afterTwoFactorChange(User|PlatformAdmin $user, string $action): void
    {
        Log::info('Superadmin : '.$action, ['admin' => $user->email]);
    }
}
