<?php

namespace App\Console\Commands;

use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Payments\SchoolFedaPay;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Filet de sécurité si un webhook FedaPay n'arrive pas : relit auprès de
 * l'API les paiements « en attente » (abonnements et frais scolaires).
 */
class SyncFedaPayPayments extends Command
{
    protected $signature = 'payments:sync-fedapay {--hours=48 : Ancienneté maximale des paiements à vérifier}';

    protected $description = 'Vérifie auprès de FedaPay les paiements en attente';

    public function handle(SubscriptionBilling $billing, TenantManager $tenancy): int
    {
        $since = now()->subHours((int) $this->option('hours'));
        $checked = 0;

        SubscriptionPayment::where('provider', 'fedapay')->where('status', 'pending')->whereNotNull('provider_reference')
            ->where('created_at', '>=', $since)->where('created_at', '<=', now()->subMinutes(5))
            ->each(function (SubscriptionPayment $payment) use ($billing, &$checked) {
                rescue(fn () => $billing->syncFromProvider($payment), report: false);
                $checked++;
            });

        Tenant::whereNotIn('status', ['suspended', 'cancelled'])->each(function (Tenant $tenant) use ($tenancy, $since, &$checked) {
            try {
                $tenancy->run($tenant, function () use ($since, &$checked) {
                    $fedapay = app(SchoolFedaPay::class);

                    if (! $fedapay->isEnabled()) {
                        return;
                    }

                    Payment::where('method', 'FedaPay')->where('status', 'pending')->whereNotNull('transaction_ref')
                        ->where('created_at', '>=', $since)->where('created_at', '<=', now()->subMinutes(5))
                        ->each(function (Payment $payment) use ($fedapay, &$checked) {
                            rescue(fn () => $fedapay->sync($payment), report: false);
                            $checked++;
                        });
                });
            } catch (Throwable $e) {
                $this->warn($tenant->code.' : '.$e->getMessage());
            }
        });

        $this->info("{$checked} paiement(s) vérifié(s).");

        return self::SUCCESS;
    }
}
