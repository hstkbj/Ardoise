<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

/*
|--------------------------------------------------------------------------
| Bases de données — Ardoise (multi-tenant, une base par école)
|--------------------------------------------------------------------------
| central : base de la plateforme (tenants, plans, abonnements, support,
|           sessions, files d'attente). C'est la connexion par défaut.
| tenant  : base de l'école courante. Son nom est injecté à chaque requête
|           par App\Tenancy\TenantManager::connect(). Vide sinon.
|
| Les deux connexions sont dérivées du pilote choisi (sqlite, mysql…), ce
| qui permet de développer en SQLite et de déployer en MySQL.
*/

$drivers = [

    'sqlite' => [
        'driver' => 'sqlite',
        'url' => env('DB_URL'),
        'database' => env('DB_DATABASE', database_path('database.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        'busy_timeout' => 5000,
        'journal_mode' => 'wal',
        'synchronous' => null,
        'transaction_mode' => 'DEFERRED',
    ],

    'mysql' => [
        'driver' => 'mysql',
        'url' => env('DB_URL'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'ardoise_central'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'unix_socket' => env('DB_SOCKET', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        ]) : [],
    ],

    'pgsql' => [
        'driver' => 'pgsql',
        'url' => env('DB_URL'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        'database' => env('DB_DATABASE', 'ardoise_central'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8'),
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => env('DB_SSLMODE', 'prefer'),
    ],
];

$centralDriver = env('DB_CONNECTION', 'sqlite');
$tenantDriver = env('TENANT_DB_CONNECTION', $centralDriver);

$tenant = $drivers[$tenantDriver];
$tenant['url'] = null;
$tenant['database'] = null; // injecté par TenantManager
$tenant['username'] = env('TENANT_DB_USERNAME', $tenant['username'] ?? null);
$tenant['password'] = env('TENANT_DB_PASSWORD', $tenant['password'] ?? null);

return [

    'default' => 'central',

    'connections' => array_merge($drivers, [
        'central' => $drivers[$centralDriver],
        'tenant' => $tenant,
    ]),

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
