<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** role:parent  ·  role:teacher,school_admin */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403, 'Accès réservé.');

        return $next($request);
    }
}
