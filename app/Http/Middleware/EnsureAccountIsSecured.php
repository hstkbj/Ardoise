<?php

namespace App\Http\Middleware;

use App\Models\Central\PlatformAdmin;
use App\Models\Tenant\User;
use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte à sécuriser avant d'utiliser l'application :
 *  - mot de passe provisoire (reçu par e-mail) à remplacer → 403 password_change_required ;
 *  - double authentification exigée par la plateforme mais pas encore activée → 403 two_factor_setup_required.
 * Seules les routes du profil, de la sécurité et de la déconnexion restent ouvertes.
 */
class EnsureAccountIsSecured
{
    /** Routes (noms) accessibles tant que le compte n'est pas sécurisé. */
    public const ALLOWED = [
        'auth.me', 'auth.logout', 'profile.password', 'profile.two-factor.*',
        'platform.auth.me', 'platform.auth.logout', 'platform.auth.password', 'platform.two-factor.*',
    ];

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = $request->user($guard);

        if (! $user instanceof User && ! $user instanceof PlatformAdmin) {
            return $next($request);
        }

        $action = SecurityPolicy::pendingAction($user);

        if ($action === null || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return response()->json([
            'message' => $action === 'password_change_required'
                ? 'Choisissez un nouveau mot de passe pour continuer : celui reçu par e-mail est provisoire.'
                : 'Activez la double authentification pour continuer : elle est obligatoire pour votre compte.',
            'code' => $action,
        ], 403);
    }
}
