<?php

namespace App\Console\Commands;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Marque les abonnements expirés et suspend les écoles après le délai de grâce';

    public function handle(): int
    {
        $expired = Subscription::whereIn('status', ['active', 'trial'])->where('expires_at', '<', now())->update(['status' => 'expired']);

        $suspended = Tenant::whereIn('status', ['active', 'trial'])
            ->where('expires_at', '<', now()->subDays(config('tenancy.grace_days')))
            ->update(['status' => 'suspended']);

        $this->info("{$expired} abonnement(s) expiré(s), {$suspended} école(s) suspendue(s).");

        return self::SUCCESS;
    }
}
