<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\User;
use App\Services\Security\TwoFactorChallenge;
use App\Services\Sms\SmsGateway;
use App\Support\Phone;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\TransientToken;

/**
 * Connexion du personnel et des enseignants (e-mail ou téléphone + mot de passe), uniquement
 * depuis l'adresse de l'établissement. Les parents utilisent l'application mobile : voir ParentAuthController.
 */
class AuthController extends Controller
{
    public function __construct(protected TenantManager $tenancy) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['boolean'],
        ]);

        $tenant = $this->requireTenantHost();

        if ($tenant->billingState() === Tenant::STATE_SUSPENDED) {
            abort(423, 'L’accès à cet établissement est suspendu.');
        }

        $this->tenancy->connect($tenant);

        $user = $this->findUser($data['login']);

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        if ($user->hasRole('parent') && ! $user->isStaff() && ! $user->hasRole('teacher')) {
            throw ValidationException::withMessages(['login' => 'Les parents se connectent avec leur code d’accès.']);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages(['login' => 'Ce compte est suspendu.']);
        }

        // Abonnement impayé : seul l'administrateur entre, pour renouveler
        if ($tenant->requiresPayment() && ! $user->hasRole('school_admin')) {
            return response()->json([
                'message' => 'L’abonnement de l’établissement a expiré. Seul l’administrateur peut se connecter pour le renouveler.',
                'code' => 'subscription_expired',
            ], 402);
        }

        // Double authentification : le mot de passe est bon, un code est encore attendu
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => app(TwoFactorChallenge::class)->create([
                    'type' => 'tenant',
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'remember' => (bool) ($data['remember'] ?? false),
                ]),
                'message' => 'Saisissez le code de votre application d’authentification.',
            ]);
        }

        $user->forceFill(['last_login_at' => now(), 'status' => 'active'])->save();

        return $this->startSession($request, $user, $tenant, (bool) ($data['remember'] ?? false), 'login');
    }

    /** POST /auth/two-factor { challenge, code | recovery_code } : 2e étape de la connexion. */
    public function twoFactor(Request $request, TwoFactorChallenge $challenges): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $challenge = $challenges->find($data['challenge']);

        if (! $challenge || ($challenge['type'] ?? null) !== 'tenant') {
            throw ValidationException::withMessages(['code' => 'Session de connexion expirée. Saisissez à nouveau votre mot de passe.']);
        }

        $tenant = Tenant::findOrFail($challenge['tenant_id']);
        abort_if($tenant->billingState() === Tenant::STATE_SUSPENDED, 423, 'L’accès à cet établissement est suspendu.');
        abort_unless(tenant() && tenant()->id === $tenant->id, 403, 'Utilisez l’adresse de votre établissement.');

        $this->tenancy->connect($tenant);
        $user = User::find($challenge['user_id']);

        if (! $user || $user->status === 'suspended') {
            $challenges->forget($data['challenge']);
            throw ValidationException::withMessages(['code' => 'Ce compte n’est plus accessible.']);
        }

        if (! $user->passesTwoFactorChallenge($data['code'] ?? null, $data['recovery_code'] ?? null)) {
            $challenges->fail($data['challenge']);
            throw ValidationException::withMessages(['code' => 'Code incorrect.']);
        }

        $challenges->forget($data['challenge']);
        $user->forceFill(['last_login_at' => now(), 'status' => 'active'])->save();

        return $this->startSession($request, $user, $tenant, (bool) $challenge['remember'], filled($data['recovery_code'] ?? null) ? 'login.recovery_code' : 'login');
    }

    public function me(Request $request): MeResource
    {
        return new MeResource($request->user()->load('roles'));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && ! $user->currentAccessToken() instanceof TransientToken) {
            $user->currentAccessToken()->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** Lien par e-mail, ou code à 6 chiffres par SMS si l'identifiant est un téléphone. */
    public function forgotPassword(Request $request, SmsGateway $sms): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
        ]);

        $this->tenancy->connect($this->requireTenantHost());

        if ($user = $this->findUser($data['login'])) {
            if (Phone::looksLikePhone($data['login']) && $user->phone) {
                $code = (string) random_int(100000, 999999);
                Cache::put($this->resetCacheKey($user), Hash::make($code), now()->addMinutes(15));
                $sms->send($user->phone, "Ardoise : votre code de réinitialisation est {$code}. Il expire dans 15 minutes.");
            } elseif ($user->email) {
                Password::broker()->sendResetLink(['email' => $user->email]);
            }
        }

        // Réponse identique que le compte existe ou non
        return response()->json(['message' => 'Si un compte correspond, un lien ou un code vient d’être envoyé.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'token' => ['required', 'string', 'max:200'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $this->tenancy->connect($this->requireTenantHost());
        $user = $this->findUser($data['login']);

        if ($user && Phone::looksLikePhone($data['login'])) {
            $hash = Cache::get($this->resetCacheKey($user));

            if (! $hash || ! Hash::check($data['token'], $hash)) {
                throw ValidationException::withMessages(['token' => 'Code invalide ou expiré.']);
            }

            Cache::forget($this->resetCacheKey($user));
            $user->forceFill(['password' => $data['password']])->save();
            $user->tokens()->delete();

            return response()->json(['message' => 'Mot de passe modifié.']);
        }

        $status = Password::broker()->reset(
            ['email' => $user?->email ?? $data['login'], 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Lien invalide ou expiré.']);
        }

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    /** Ouvre une session (SPA) ou délivre un jeton (application mobile / client API). */
    public static function startSession(Request $request, User $user, Tenant $tenant, bool $remember, string $action): JsonResponse
    {
        ActivityLog::record($action, $user);

        if ($request->hasSession()) {
            Auth::guard('web')->login($user, $remember);
            $request->session()->regenerate();
            $request->session()->put('tenant_id', $tenant->id);

            return response()->json(['data' => (new MeResource($user->load('roles')))->resolve($request)]);
        }

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return response()->json([
            'data' => (new MeResource($user->load('roles')))->resolve($request),
            'token' => TenantResolver::prefixToken($tenant, $token),
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Le personnel et les enseignants se connectent uniquement depuis l'adresse de leur
     * établissement (sous-domaine ou domaine personnalisé). Le domaine central est
     * réservé au superadmin.
     */
    protected function requireTenantHost(): Tenant
    {
        $tenant = tenant();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'login' => 'Connectez-vous depuis l’adresse de votre établissement (ex. https://votre-ecole.'.config('tenancy.base_domain').'). Cette adresse est réservée à la plateforme.',
            ]);
        }

        return $tenant;
    }

    protected function findUser(string $login): ?User
    {
        $login = trim($login);

        return Phone::looksLikePhone($login)
            ? User::where('phone', Phone::normalize($login))->first()
            : User::where('email', strtolower($login))->first();
    }

    protected function resetCacheKey(User $user): string
    {
        return 'pwd-reset:'.tenant()?->id.':'.$user->id;
    }
}
