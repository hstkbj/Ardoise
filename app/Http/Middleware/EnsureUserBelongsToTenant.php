<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après l'authentification : vérifie que la session a bien été ouverte pour
 * cette école et que le compte est actif.
 */
class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        if (! $user || ! $tenant) {
            abort(401);
        }

        if ($request->hasSession() && Auth::guard('web')->check()) {
            $sessionTenant = $request->session()->get('tenant_id');

            if ($sessionTenant !== null && (int) $sessionTenant !== (int) $tenant->id) {
                Auth::guard('web')->logout();
                abort(401, 'Session invalide pour cet établissement.');
            }
        }

        if ($user->status !== 'active') {
            abort(403, 'Votre compte est désactivé.');
        }

        return $next($request);
    }
}
