<?php

namespace App\Console\Commands;

use App\Actions\CreateTenant;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\PlatformStatsService;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Tenant\DemoSchoolSeeder;
use Illuminate\Console\Command;

/** php artisan ardoise:demo — base centrale + école de démonstration complète. */
class DemoSetup extends Command
{
    protected $signature = 'ardoise:demo {--code=palmiers} {--fresh : Supprime puis recrée l\'école de démonstration}';

    protected $description = 'Crée une école de démonstration avec des données réalistes (développement)';

    public function handle(CreateTenant $create, TenantManager $manager, DatabaseCreator $databases, PlatformStatsService $stats): int
    {
        if (app()->environment('production')) {
            $this->error('Commande réservée au développement.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $code = $this->option('code');

        if ($existing = Tenant::where('code', $code)->first()) {
            if (! $this->option('fresh')) {
                $this->warn("L'école « {$code} » existe déjà (utilisez --fresh pour la recréer).");

                return self::SUCCESS;
            }

            rescue(fn () => $databases->drop($existing));
            $existing->delete();
        }

        $result = $create->handle([
            'code' => $code,
            'name' => 'GS Les Palmiers',
            'admin_name' => 'Aminata Koné',
            'admin_email' => 'admin@lespalmiers.ci',
            'admin_password' => 'password',
            'status' => 'active',
            'expires_at' => now()->addYear()->toDateString(),
            'send_credentials' => false,
            // Formule complète : tous les modules sont visibles dans la démonstration
            'plan_id' => Plan::where('name', 'Groupe scolaire')->value('id'),
        ]);

        $tenant = $result['tenant'];
        $this->components->task('Données de démonstration', fn () => $manager->run($tenant, fn () => (new DemoSchoolSeeder)->run()) ?? true);
        $stats->snapshot($tenant);

        $base = config('tenancy.base_domain');
        $this->newLine();
        $this->components->info('École de démonstration prête');
        $this->table(['Espace', 'Adresse', 'Identifiant', 'Mot de passe / code'], [
            ['Administration', "http://{$code}.{$base}:8000/login", 'admin@lespalmiers.ci', 'password'],
            ['Enseignant', "http://{$code}.{$base}:8000/login", 'm.diallo@lespalmiers.ci', 'password'],
            ['Superadmin', "http://{$base}:8000/login", config('ardoise.superadmin.email'), config('ardoise.superadmin.password') ?: '(affiché par db:seed)'],
        ]);
        $this->line('Codes parents (connexion par code, sur n’importe quel domaine) :');
        $this->table(['Parent', 'Code'], collect(DemoSchoolSeeder::$parentCodes)->reverse()->take(5)->values()->all());

        return self::SUCCESS;
    }
}
