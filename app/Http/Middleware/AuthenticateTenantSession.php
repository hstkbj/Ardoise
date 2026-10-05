<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantSession extends AuthenticateSession
{
    public function __construct(
        AuthFactory $auth,
        protected TenantResolver $resolver,
        protected TenantManager $tenancy,
    ) {
        parent::__construct($auth);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (! $tenant) {
            return parent::handle($request, $next);
        }

        $this->tenancy->connect($tenant);

        return parent::handle($request, $next);
    }
}
