<?php

namespace App\Console\Commands;

use App\Actions\CreateTenant;
use Illuminate\Console\Command;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create {code} {name} {--admin-name=Administrateur} {--admin-email=} {--admin-password=} {--plan=} {--active}';

    protected $description = 'Crée une école : base de données, migrations, administrateur, abonnement';

    public function handle(CreateTenant $action): int
    {
        $email = $this->option('admin-email') ?: $this->ask('E-mail de l\'administrateur');

        $result = $action->handle([
            'code' => $this->argument('code'),
            'name' => $this->argument('name'),
            'admin_name' => $this->option('admin-name'),
            'admin_email' => $email,
            'admin_password' => $this->option('admin-password'),
            'plan_id' => $this->option('plan'),
            'status' => $this->option('active') ? 'active' : 'trial',
        ]);

        $tenant = $result['tenant'];
        $this->components->info("École « {$tenant->name} » créée.");
        $this->table(['Adresse', 'Administrateur', 'Mot de passe'], [[$tenant->primaryDomain(), $email, $result['admin_password']]]);

        return self::SUCCESS;
    }
}
