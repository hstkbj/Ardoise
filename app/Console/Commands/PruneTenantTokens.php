<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\PersonalAccessToken;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class PruneTenantTokens extends Command
{
    protected $signature = 'tenants:prune-tokens';

    protected $description = 'Supprime les jetons mobiles expirés dans chaque école';

    public function handle(TenantManager $manager): int
    {
        Tenant::where('status', '!=', 'cancelled')->each(function (Tenant $tenant) use ($manager) {
            $count = $manager->run($tenant, fn () => PersonalAccessToken::where('expires_at', '<', now())
                ->orWhere('created_at', '<', now()->subMinutes((int) config('sanctum.expiration', 259200)))
                ->delete());
            $this->line("{$tenant->code} : {$count} jeton(s) supprimé(s)");
        });

        return self::SUCCESS;
    }
}
