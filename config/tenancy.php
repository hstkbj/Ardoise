<?php

return [

    /*
    | Domaines « centraux » : site public, console superadmin, et point
    | d'entrée de l'application parents (connexion par code).
    */
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1'))))),

    /*
    | Domaine de base des sous-domaines écoles : {code}.{base_domain}.
    | En local, « localhost » fonctionne : palmiers.localhost:8000
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    'database' => [
        // Préfixe des bases MySQL/PostgreSQL : tenant_palmiers
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
        // Dossier des bases SQLite (développement)
        'sqlite_path' => env('TENANCY_SQLITE_PATH', database_path('tenants')),
        // Créer un utilisateur MySQL dédié par école (droits limités à sa base)
        'create_users' => (bool) env('TENANCY_CREATE_DB_USERS', false),
        'migrations_path' => 'database/tenant/migrations',
    ],

    // Durée d'essai par défaut (jours) à la création d'une école
    'trial_days' => (int) env('TENANCY_TRIAL_DAYS', 30),

    // Délai de grâce après expiration de l'abonnement (jours)
    'grace_days' => (int) env('TENANCY_GRACE_DAYS', 15),
];
