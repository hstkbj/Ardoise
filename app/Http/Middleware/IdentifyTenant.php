<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'école courante et connecte sa base.
 *   tenant           → obligatoire (404 si aucune école)
 *   tenant:optional  → connecte si possible (connexion par code parent, page publique)
 */
class IdentifyTenant
{
    public function __construct(protected TenantResolver $resolver, protected TenantManager $manager) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (! $tenant) {
            abort_if($mode === 'required', 404, 'École introuvable. Vérifiez l’adresse ou reconnectez-vous.');

            return $next($request);
        }

        $this->manager->connect($tenant);
        $this->resolver->stripTokenPrefix($request);

        return $next($request);
    }

    public function terminate(): void
    {
        $this->manager->disconnect();
    }
}
