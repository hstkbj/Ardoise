<?php

namespace App\Support;

use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use App\Models\Tenant\User;

/**
 * Règles de sécurité des comptes, réglables dans la console plateforme
 * (Paramètres → Sécurité) :
 *  - require_2fa_platform      : double authentification obligatoire pour les superadmins ;
 *  - require_2fa_school_admins : obligatoire pour les administrateurs et directeurs d'école.
 */
final class SecurityPolicy
{
    public const ADMIN_ROLES = ['school_admin', 'director'];

    public static function requiresTwoFactor(User|PlatformAdmin $user): bool
    {
        if ($user instanceof PlatformAdmin) {
            return PlatformSetting::flag('security', 'require_2fa_platform');
        }

        return $user->hasAnyRole(self::ADMIN_ROLES)
            && PlatformSetting::flag('security', 'require_2fa_school_admins');
    }

    /** Compte autorisé à activer la double authentification (compte avec mot de passe). */
    public static function canUseTwoFactor(User|PlatformAdmin $user): bool
    {
        return $user instanceof PlatformAdmin || ($user->password !== null && ($user->isStaff() || $user->hasRole('teacher')));
    }

    /**
     * Action bloquante avant d'utiliser l'application, ou null.
     *
     * @return 'password_change_required'|'two_factor_setup_required'|null
     */
    public static function pendingAction(User|PlatformAdmin $user): ?string
    {
        if ($user instanceof User && $user->must_change_password) {
            return 'password_change_required';
        }

        if (self::requiresTwoFactor($user) && ! $user->hasTwoFactorEnabled()) {
            return 'two_factor_setup_required';
        }

        return null;
    }

    /** @return array{must_change_password: bool, two_factor_enabled: bool, two_factor_required: bool, two_factor_available: bool, recovery_codes_left: int, pending_action: string|null} */
    public static function summary(User|PlatformAdmin $user): array
    {
        return [
            'must_change_password' => $user instanceof User && (bool) $user->must_change_password,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'two_factor_required' => self::requiresTwoFactor($user),
            'two_factor_available' => self::canUseTwoFactor($user),
            'recovery_codes_left' => $user->hasTwoFactorEnabled() ? $user->remainingRecoveryCodes() : 0,
            'pending_action' => self::pendingAction($user),
        ];
    }
}
