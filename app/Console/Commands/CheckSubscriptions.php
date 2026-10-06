<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReminderMail;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Tâche quotidienne : met à jour le statut des abonnements et envoie les
 * rappels à l'administrateur de chaque école (fin proche, délai de grâce,
 * accès bloqué). Chaque rappel n'est envoyé qu'une fois.
 */
class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check {--no-mail : Ne pas envoyer de rappels}';

    protected $description = 'Met à jour les abonnements expirés et envoie les rappels de renouvellement';

    public function handle(): int
    {
        $expired = Subscription::whereIn('status', ['active', 'trial'])->where('expires_at', '<', now())->update(['status' => 'expired']);
        $reminders = 0;

        Tenant::whereNotIn('status', ['suspended', 'cancelled'])->whereNotNull('admin_email')->each(function (Tenant $tenant) use (&$reminders) {
            $kind = $this->reminderFor($tenant);

            if ($kind === null || $this->option('no-mail')) {
                return;
            }

            $key = $kind.':'.$tenant->billingEndsAt()?->toDateString().($kind === 'ending' ? ':'.$this->daysLeft($tenant) : '');
            $data = $tenant->data ?? [];

            if (in_array($key, $data['billing_reminders'] ?? [], true)) {
                return;
            }

            Mail::to($tenant->admin_email)->queue(new SubscriptionReminderMail($tenant, $kind));
            $data['billing_reminders'] = array_slice([...($data['billing_reminders'] ?? []), $key], -20);
            $tenant->update(['data' => $data]);
            $reminders++;
        });

        $this->info("{$expired} abonnement(s) expiré(s), {$reminders} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    /** ending (J-7, J-3, J-1 par défaut) | grace | expired | null */
    protected function reminderFor(Tenant $tenant): ?string
    {
        return match ($tenant->billingState()) {
            Tenant::STATE_EXPIRED => 'expired',
            Tenant::STATE_GRACE => 'grace',
            Tenant::STATE_TRIAL, Tenant::STATE_ACTIVE => $tenant->billingEndsAt() && in_array($this->daysLeft($tenant), config('ardoise.billing.reminder_days'), true) ? 'ending' : null,
            default => null,
        };
    }

    protected function daysLeft(Tenant $tenant): int
    {
        return (int) ceil(now()->diffInDays($tenant->billingEndsAt(), false));
    }
}
