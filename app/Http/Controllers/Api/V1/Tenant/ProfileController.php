<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Concerns\ManagesTwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use ManagesTwoFactor;

    public function update(Request $request): MeResource
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('tenant.users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Les parents mettent à jour leurs coordonnées de contact, pas d'identifiants de connexion
        if ($profile = $user->parentProfile) {
            $profile->update(['phone' => $data['phone'] ?? $profile->phone, 'email' => $data['email'] ?? $profile->email]);
            $user->update(['name' => $data['name']]);
        } else {
            $user->update(['name' => $data['name'], 'email' => isset($data['email']) ? strtolower($data['email']) : $user->email, 'phone' => Phone::normalize($data['phone'] ?? null) ?? $user->phone]);
        }

        return new MeResource($user->load('roles'));
    }

    public function password(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('parent') && ! $user->password, 422, 'Les parents se connectent avec leur code d’accès.');

        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current'],
        ], ['password.different' => 'Choisissez un mot de passe différent du mot de passe actuel.']);

        if (! Hash::check($data['current'], (string) $user->password)) {
            throw ValidationException::withMessages(['current' => 'Mot de passe actuel incorrect.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        // Les autres appareils (jetons mobiles) sont déconnectés ; la session en cours est conservée
        $current = $user->currentAccessToken();
        $user->tokens()->when($current && method_exists($current, 'getKey'), fn ($q) => $q->whereKeyNot($current->getKey()))->delete();
        ActivityLog::record('security.password_changed', $user);

        return response()->json(['message' => 'Mot de passe modifié.', 'data' => (new MeResource($user->load('roles')))->resolve($request)]);
    }

    protected function twoFactorUser(Request $request): User|PlatformAdmin
    {
        return $request->user();
    }

    protected function afterTwoFactorChange(User|PlatformAdmin $user, string $action): void
    {
        ActivityLog::record($action, $user);
    }
}
