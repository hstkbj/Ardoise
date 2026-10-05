<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantMigrator;
use Illuminate\Console\Command;
use Throwable;

class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate {--tenant=* : Code(s) d\'école} {--fresh : Recrée toutes les tables} {--seed : Rejoue les données de base}';

    protected $description = 'Exécute les migrations dans la base de chaque école';

    public function handle(TenantMigrator $migrator): int
    {
        $codes = $this->option('tenant');
        $tenants = Tenant::when($codes, fn ($q) => $q->whereIn('code', $codes))->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->warn('Aucune école trouvée.');

            return self::SUCCESS;
        }

        if ($this->option('fresh') && ! $this->confirm('Toutes les données des écoles sélectionnées seront effacées. Continuer ?', app()->environment('local', 'testing'))) {
            return self::FAILURE;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->components->task("{$tenant->code} ({$tenant->database})", function () use ($migrator, $tenant, &$failed) {
                try {
                    $migrator->migrate($tenant, (bool) $this->option('fresh'));
                    $this->option('seed') && $migrator->seed($tenant);

                    return true;
                } catch (Throwable $e) {
                    $failed++;
                    $this->error($e->getMessage());

                    return false;
                }
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
