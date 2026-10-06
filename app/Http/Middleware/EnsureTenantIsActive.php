<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle de l'abonnement de l'école (après l'authentification).
 *
 *  - suspendue / résiliée par la plateforme : 423 pour tous ;
 *  - impayée après le délai de grâce : 402 pour tous, sauf l'administrateur
 *    sur les routes de renouvellement (billing.*) et de session.
 */
class EnsureTenantIsActive
{
    /** Routes accessibles à l'administrateur quand l'abonnement doit être payé. */
    protected const PAYMENT_ROUTES = ['billing.*', 'auth.me', 'auth.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            return $next($request);
        }

        $state = $tenant->billingState();

        if ($state === Tenant::STATE_SUSPENDED) {
            abort(423, 'L’accès à cet établissement est suspendu. Contactez votre administration.');
        }

        if ($state === Tenant::STATE_EXPIRED && ! $this->canRenew($request)) {
            return response()->json([
                'message' => 'L’abonnement de l’établissement a expiré. L’administrateur doit le renouveler pour rétablir l’accès.',
                'code' => 'subscription_expired',
            ], 402);
        }

        return $next($request);
    }

    protected function canRenew(Request $request): bool
    {
        return $request->user()?->hasRole('school_admin') && $request->routeIs(...self::PAYMENT_ROUTES);
    }
}
