<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Connecte l'application à la base de l'école courante.
 *
 * Toutes les classes de App\Models\Tenant utilisent la connexion « tenant » :
 * tant qu'aucune école n'est connectée, cette connexion n'a pas de base et
 * toute requête échoue — impossible de lire par erreur une autre base.
 */
class TenantManager
{
    protected ?Tenant $tenant = null;

    protected array $template;

    public function __construct()
    {
        $this->template = config('database.connections.tenant');
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function connect(Tenant $tenant): void
    {
        if ($this->tenant?->is($tenant)) {
            return;
        }

        $config = $this->template;
        $config['database'] = $this->databasePath($tenant);

        if ($tenant->db_username) {
            $config['username'] = $tenant->db_username;
            $config['password'] = $tenant->db_password;
        }

        DB::purge('tenant');
        config(['database.connections.tenant' => $config]);

        $this->tenant = $tenant;
    }

    public function disconnect(): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant' => $this->template]);
        $this->tenant = null;
    }

    /** Exécute un traitement dans la base d'une école puis restaure le contexte précédent. */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;

        $this->connect($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->forceConnect($previous) : $this->disconnect();
        }
    }

    protected function forceConnect(Tenant $tenant): void
    {
        $this->tenant = null;
        $this->connect($tenant);
    }

    /** Nom de la base (MySQL/PostgreSQL) ou chemin du fichier (SQLite). */
    public function databasePath(Tenant $tenant): string
    {
        if ($this->driver() === 'sqlite') {
            return rtrim(config('tenancy.database.sqlite_path'), '/').'/'.$tenant->database.'.sqlite';
        }

        return $tenant->database;
    }

    public function driver(): string
    {
        return $this->template['driver'];
    }
}
