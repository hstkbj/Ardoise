<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/** Crée / supprime physiquement la base d'une école (SQLite, MySQL, PostgreSQL). */
class DatabaseCreator
{
    public function __construct(protected TenantManager $manager) {}

    public static function databaseNameFor(string $code): string
    {
        return config('tenancy.database.prefix').str_replace('-', '_', $code);
    }

    public function create(Tenant $tenant): void
    {
        $name = $tenant->database;
        $this->guardName($name);

        match ($this->manager->driver()) {
            'sqlite' => $this->createSqlite($tenant),
            'mysql', 'mariadb' => $this->createMysql($tenant, $name),
            'pgsql' => DB::connection('central')->statement("CREATE DATABASE \"{$name}\" ENCODING 'UTF8'"),
            default => throw new RuntimeException('Pilote de base non pris en charge pour les écoles.'),
        };
    }

    public function drop(Tenant $tenant): void
    {
        $name = $tenant->database;
        $this->guardName($name);

        match ($this->manager->driver()) {
            'sqlite' => File::delete($this->manager->databasePath($tenant)),
            'mysql', 'mariadb' => $this->dropMysql($tenant, $name),
            'pgsql' => DB::connection('central')->statement("DROP DATABASE IF EXISTS \"{$name}\""),
            default => null,
        };
    }

    public function exists(Tenant $tenant): bool
    {
        return match ($this->manager->driver()) {
            'sqlite' => File::exists($this->manager->databasePath($tenant)),
            'mysql', 'mariadb' => (bool) DB::connection('central')->selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$tenant->database]),
            'pgsql' => (bool) DB::connection('central')->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$tenant->database]),
            default => false,
        };
    }

    protected function createSqlite(Tenant $tenant): void
    {
        $path = $this->manager->databasePath($tenant);
        File::ensureDirectoryExists(dirname($path));

        if (File::exists($path)) {
            throw new RuntimeException("La base {$tenant->database} existe déjà.");
        }

        File::put($path, '');
    }

    protected function createMysql(Tenant $tenant, string $name): void
    {
        $central = DB::connection('central');
        $central->statement("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if (! config('tenancy.database.create_users')) {
            return;
        }

        // Utilisateur MySQL dédié : ses droits se limitent à la base de l'école
        $user = Str::limit($name, 28, '').'_u';
        $password = Str::password(32, symbols: false);

        $central->statement("CREATE USER '{$user}'@'%' IDENTIFIED BY '{$password}'");
        $central->statement("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'%'");
        $central->statement('FLUSH PRIVILEGES');

        $tenant->forceFill(['db_username' => $user, 'db_password' => $password])->save();
    }

    protected function dropMysql(Tenant $tenant, string $name): void
    {
        $central = DB::connection('central');
        $central->statement("DROP DATABASE IF EXISTS `{$name}`");

        if ($tenant->db_username) {
            $central->statement("DROP USER IF EXISTS '{$tenant->db_username}'@'%'");
        }
    }

    protected function guardName(string $name): void
    {
        if (! preg_match('/^[a-z0-9_]{3,64}$/', $name)) {
            throw new RuntimeException("Nom de base invalide : {$name}");
        }
    }
}
