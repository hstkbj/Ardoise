<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class TenantsList extends Command
{
    protected $signature = 'tenants:list';

    protected $description = 'Liste les écoles';

    public function handle(): int
    {
        $this->table(['Code', 'Nom', 'Base', 'Statut', 'Expire le'], Tenant::orderBy('code')->get()->map(fn ($t) => [
            $t->code, $t->name, $t->database, $t->status, $t->expires_at?->toDateString() ?? '—',
        ]));

        return self::SUCCESS;
    }
}
