<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantAdminCheck extends Command
{
    protected $signature = 'tenant:admin-check {code} {--password= : Mot de passe à tester} {--reset : Génère et enregistre un nouveau mot de passe}';

    protected $description = 'Vérifie l’administrateur d’une école (présence, mot de passe enregistré) ; --reset définit un nouveau mot de passe';

    public function handle(TenantManager $tenancy): int
    {
        $tenant = Tenant::where('code', $this->argument('code'))->first();

        if (! $tenant) {
            $this->components->error('École introuvable. Codes existants : '.Tenant::pluck('code')->implode(', '));

            return self::FAILURE;
        }

        $this->line('Adresse de connexion : '.$tenant->url('/login'));
        $this->line('Domaines enregistrés : '.$tenant->domains()->pluck('domain')->implode(', '));

        return $tenancy->run($tenant, function () use ($tenant) {
            $admin = User::where('email', $tenant->admin_email)->first()
                ?? User::whereHas('roles', fn ($q) => $q->where('key', 'school_admin'))->orderBy('id')->first();

            if (! $admin) {
                $this->components->error('Aucun administrateur dans la base de cette école.');

                return self::FAILURE;
            }

            $this->table(['E-mail', 'Statut', 'Mot de passe haché', 'À changer', '2FA'], [[
                $admin->email,
                $admin->status,
                $admin->password && Hash::info($admin->password)['algoName'] !== 'unknown' ? 'oui' : 'NON',
                $admin->must_change_password ? 'oui' : 'non',
                $admin->hasTwoFactorEnabled() ? 'oui' : 'non',
            ]]);

            if ($this->option('reset')) {
                $password = Str::password(12, symbols: false);
                $admin->forceFill(['password' => $password, 'must_change_password' => true, 'status' => 'active'])->save();
                $this->components->info('Nouveau mot de passe enregistré : '.$password);

                return self::SUCCESS;
            }

            if (filled($this->option('password'))) {
                Hash::check($this->option('password'), $admin->password)
                    ? $this->components->info('Ce mot de passe correspond.')
                    : $this->components->error('Ce mot de passe ne correspond pas.');
            }

            return self::SUCCESS;
        });
    }
}
