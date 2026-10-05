<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloque l'accès si l'abonnement de l'école est suspendu, annulé ou expiré. */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && ! $tenant->isAccessible()) {
            abort(423, 'L’accès à cet établissement est suspendu. Contactez votre administration.');
        }

        return $next($request);
    }
}
