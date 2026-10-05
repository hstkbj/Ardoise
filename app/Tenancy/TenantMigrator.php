<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;

/** Exécute les migrations / seeders dans la base d'une école. */
class TenantMigrator
{
    public function __construct(protected TenantManager $manager) {}

    public function migrate(Tenant $tenant, bool $fresh = false): string
    {
        return $this->manager->run($tenant, function () use ($fresh) {
            Artisan::call($fresh ? 'migrate:fresh' : 'migrate', [
                '--database' => 'tenant',
                '--path' => config('tenancy.database.migrations_path'),
                '--force' => true,
            ]);

            return Artisan::output();
        });
    }

    public function seed(Tenant $tenant, string $class = \Database\Seeders\Tenant\TenantDatabaseSeeder::class): string
    {
        return $this->manager->run($tenant, function () use ($class) {
            Artisan::call('db:seed', ['--database' => 'tenant', '--class' => $class, '--force' => true]);

            return Artisan::output();
        });
    }
}
