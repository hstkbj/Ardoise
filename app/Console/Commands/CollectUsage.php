<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\PlatformStatsService;
use Illuminate\Console\Command;

class CollectUsage extends Command
{
    protected $signature = 'platform:collect-usage {--tenant=*}';

    protected $description = 'Enregistre l\'instantané d\'utilisation de chaque école (statistiques plateforme)';

    public function handle(PlatformStatsService $stats): int
    {
        Tenant::when($this->option('tenant'), fn ($q, $codes) => $q->whereIn('code', $codes))
            ->whereNotIn('status', ['cancelled'])
            ->each(fn (Tenant $t) => $this->components->task($t->code, fn () => $stats->snapshot($t) !== null));

        return self::SUCCESS;
    }
}
