<?php

namespace App\Actions;

use App\Mail\TenantWelcomeMail;
use App\Models\Central\Domain;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Tenant\Role;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Tenancy\DatabaseCreator;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantMigrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Création d'une école :
 *   tenant + domaine → base de données → (utilisateur DB) → migrations
 *   → rôles/permissions/niveaux/année → administrateur → abonnement.
 * En cas d'échec, tout est annulé (base supprimée, tenant effacé).
 */
class CreateTenant
{
    public function __construct(
        protected DatabaseCreator $databases,
        protected TenantMigrator $migrator,
        protected TenantManager $manager,
    ) {}

    /**
     * @param  array{name: string, code?: string|null, admin_name: string, admin_email: string, plan_id?: int|null, status?: string, expires_at?: string|null, domain?: string|null, admin_password?: string|null, send_credentials?: bool}  $data
     * @return array{tenant: Tenant, admin_password: string}
     */
    public function handle(array $data): array
    {
        $code = filled($data['code'] ?? null)
            ? Str::lower(trim($data['code']))
            : $this->generateCode($data['name']);

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $code) || in_array($code, ['www', 'admin', 'api', 'app', 'mail'], true)) {
            throw ValidationException::withMessages(['code' => 'Code invalide : lettres minuscules, chiffres et tirets (3 à 40 caractères).']);
        }

        if (Tenant::where('code', $code)->exists() || Domain::where('domain', $code.'.'.config('tenancy.base_domain'))->exists()) {
            throw ValidationException::withMessages(['code' => 'Ce code ou son sous-domaine est déjà utilisé.']);
        }

        $status = $data['status'] ?? 'trial';
        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : Plan::orderBy('id')->first();
        $trialEnds = $status === 'trial' ? now()->addDays(config('tenancy.trial_days')) : null;

        $tenant = Tenant::create([
            'name' => $data['name'],
            'code' => $code,
            'database' => DatabaseCreator::databaseNameFor($code),
            'status' => $status,
            'plan_id' => $plan?->id,
            'trial_ends_at' => $trialEnds,
            'expires_at' => $data['expires_at'] ?? $trialEnds,
            'admin_name' => $data['admin_name'],
            'admin_email' => strtolower($data['admin_email']),
        ]);

        $password = $data['admin_password'] ?? Str::password(12, symbols: false);
        $databaseCreated = false;

        try {
            $tenant->domains()->create(['domain' => $code.'.'.config('tenancy.base_domain')]);

            if (! empty($data['domain'])) {
                $tenant->domains()->create(['domain' => Str::lower($data['domain'])]);
            }

            $this->databases->create($tenant);
            $databaseCreated = true;

            $this->migrator->migrate($tenant->refresh());
            $this->migrator->seed($tenant);

            $this->manager->run($tenant, function () use ($tenant, $data, $password) {
                $admin = User::create([
                    'name' => $data['admin_name'],
                    'email' => strtolower($data['admin_email']),
                    'password' => $password,
                    'status' => 'active',
                ]);
                $admin->roles()->attach(Role::where('key', 'school_admin')->value('id'));
                Setting::put('identity', ['name' => $tenant->name]);
                Setting::put('contact', ['email' => strtolower($data['admin_email'])]);
            });

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan?->id,
                'status' => $status === 'active' ? 'active' : 'trial',
                'starts_at' => now(),
                'expires_at' => $tenant->expires_at,
                'amount' => $plan?->price,
            ]);
        } catch (Throwable $e) {
            if ($databaseCreated) {
                rescue(fn () => $this->databases->drop($tenant));
            }

            $tenant->delete();

            throw $e;
        }

        Cache::forget("tenancy:host:{$code}.".config('tenancy.base_domain'));
        $tenant->refresh();

        // Identifiants, sous-domaine et lien de connexion envoyés à l'administrateur (file d'attente)
        if ($data['send_credentials'] ?? true) {
            Mail::to($tenant->admin_email)->queue(new TenantWelcomeMail($tenant, $password));
        }

        return ['tenant' => $tenant, 'admin_password' => $password];
    }

    protected function generateCode(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'ecole';
        } elseif (strlen($base) < 3 || in_array($base, ['www', 'admin', 'api', 'app', 'mail'], true)) {
            $base .= '-ecole';
        }

        $base = rtrim(substr($base, 0, 40), '-');
        $suffix = 1;

        do {
            $suffixText = $suffix === 1 ? '' : '-'.$suffix;
            $code = rtrim(substr($base, 0, 40 - strlen($suffixText)), '-').$suffixText;
            $suffix++;
        } while (
            Tenant::where('code', $code)->exists()
            || Domain::where('domain', $code.'.'.config('tenancy.base_domain'))->exists()
        );

        return $code;
    }
}
