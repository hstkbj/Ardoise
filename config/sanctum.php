<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    | Domaines servis en mode SPA (cookies de session). Les jokers sont acceptés :
    | *.ardoise.app couvre toutes les écoles.
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:8000,*.localhost,*.localhost:8000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    // Garde utilisée pour les requêtes SPA des écoles (utilisateurs tenant)
    'guard' => ['web'],

    // Jetons des applications mobiles (parents) : 180 jours
    'expiration' => env('SANCTUM_EXPIRATION', 60 * 24 * 180),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => App\Http\Middleware\AuthenticateTenantSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
