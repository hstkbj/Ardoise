<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\User;
use App\Support\SecurityPolicy;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Activation / désactivation de la double authentification par l'utilisateur
 * connecté (personnel d'une école ou superadmin).
 *
 *   GET    …/two-factor                 → état
 *   POST   …/two-factor/setup           → secret + URI otpauth (QR code)
 *   POST   …/two-factor/confirm         { code } → activée + codes de secours
 *   POST   …/two-factor/recovery-codes  { password } → nouveaux codes de secours
 *   DELETE …/two-factor                 { password } → désactivée
 */
trait ManagesTwoFactor
{
    abstract protected function twoFactorUser(Request $request): User|PlatformAdmin;

    abstract protected function afterTwoFactorChange(User|PlatformAdmin $user, string $action): void;

    public function showTwoFactor(Request $request): JsonResponse
    {
        return response()->json(['data' => SecurityPolicy::summary($this->twoFactorUser($request))]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->twoFactorUser($request);
        abort_unless(SecurityPolicy::canUseTwoFactor($user), 422, 'La double authentification n’est pas disponible pour ce compte.');
        abort_if($user->hasTwoFactorEnabled(), 422, 'La double authentification est déjà active.');

        $secret = $user->startTwoFactorSetup();

        return response()->json(['data' => [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'otpauth_url' => Totp::uri($secret, (string) ($user->email ?? $user->phone ?? $user->name), (string) config('app.name', 'Ardoise')),
        ]]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $user = $this->twoFactorUser($request);
        $code = $request->validate(['code' => ['required', 'string', 'max:20']])['code'];

        abort_if($user->hasTwoFactorEnabled(), 422, 'La double authentification est déjà active.');
        abort_unless(filled($user->two_factor_secret), 422, 'Commencez par scanner le QR code.');

        if (! $user->verifyTwoFactorCode($code)) {
            throw ValidationException::withMessages(['code' => 'Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.']);
        }

        $codes = $user->confirmTwoFactor();
        $this->afterTwoFactorChange($user, 'security.two_factor_enabled');

        return response()->json(['message' => 'Double authentification activée.', 'data' => ['recovery_codes' => $codes] + SecurityPolicy::summary($user)]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $this->twoFactorUser($request);
        $this->ensurePassword($request, $user);
        abort_unless($user->hasTwoFactorEnabled(), 422, 'La double authentification n’est pas active.');

        $codes = $user->regenerateRecoveryCodes();
        $this->afterTwoFactorChange($user, 'security.recovery_codes_regenerated');

        return response()->json(['message' => 'Nouveaux codes de secours générés.', 'data' => ['recovery_codes' => $codes] + SecurityPolicy::summary($user)]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->twoFactorUser($request);
        $this->ensurePassword($request, $user);
        abort_if(SecurityPolicy::requiresTwoFactor($user), 422, 'La double authentification est obligatoire pour votre compte.');

        $user->disableTwoFactor();
        $this->afterTwoFactorChange($user, 'security.two_factor_disabled');

        return response()->json(['message' => 'Double authentification désactivée.', 'data' => SecurityPolicy::summary($user)]);
    }

    protected function ensurePassword(Request $request, User|PlatformAdmin $user): void
    {
        $password = $request->validate(['password' => ['required', 'string']])['password'];

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }
    }
}
