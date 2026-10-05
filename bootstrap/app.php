<?php

use App\Http\Middleware\CentralDomainOnly;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sessions + CSRF pour la SPA (Sanctum) ; jetons Bearer pour l'app mobile
        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
            'central' => CentralDomainOnly::class,
            'role' => EnsureRole::class,
        ]);

        // Connecter la base de l'école avant que la session DB recharge son utilisateur.
        $middleware->prependToGroup('web', IdentifyTenant::class.':optional');

        // L'école doit être connectée AVANT l'authentification (utilisateurs en base école)
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyTenant::class);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
