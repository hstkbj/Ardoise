<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Routes de la console superadmin : uniquement sur le domaine central. */
class CentralDomainOnly
{
    public function __construct(protected TenantResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->resolver->isCentralHost($request->getHost()), 404);

        return $next($request);
    }
}
