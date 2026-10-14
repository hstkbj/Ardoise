<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReminderMail;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\PlatformAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Tâche quotidienne : met à jour le statut des abonnements et envoie les
 * rappels à l'administrateur de chaque école (fin proche, délai de grâce,
 * accès bloqué). Chaque rappel n'est envoyé qu'une fois.
 * Les superadmins sont prévenus des abonnements qui arrivent à échéance,
 * passent en délai de grâce ou expirent (une alerte par école et par échéance).
 */
class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check {--no-mail : Ne pas envoyer de rappels aux écoles} {--no-alerts : Ne pas alerter les superadmins}';

    protected $description = 'Met à jour les abonnements expirés et envoie les rappels de renouvellement';

    public function handle(PlatformAlerts $alerts): int
    {
        $expired = Subscription::whereIn('status', ['active', 'trial'])->where('expires_at', '<', now())->update(['status' => 'expired']);
        $reminders = 0;
        $platformAlerts = 0;

        Tenant::whereNotIn('status', ['suspended', 'cancelled'])->each(function (Tenant $tenant) use ($alerts, &$platformAlerts) {
            if (! $this->option('no-alerts') && $this->alertPlatform($alerts, $tenant)) {
                $platformAlerts++;
            }
        });

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

        $this->info("{$expired} abonnement(s) expiré(s), {$reminders} rappel(s) envoyé(s), {$platformAlerts} alerte(s) plateforme.");

        return self::SUCCESS;
    }

    /** Une alerte par école et par échéance : fin proche (premier rappel), délai de grâce, blocage. */
    protected function alertPlatform(PlatformAlerts $alerts, Tenant $tenant): bool
    {
        $end = $tenant->billingEndsAt();

        if (! $end) {
            return false;
        }

        $date = $end->format('d/m/Y');
        $what = $tenant->status === 'trial' ? 'L’essai' : 'L’abonnement';
        $link = '/superadmin/tenants/'.$tenant->id;

        return match ($tenant->billingState()) {
            Tenant::STATE_EXPIRED => $alerts->once($tenant, 'expired:'.$end->toDateString(), 'subscription.expired',
                'École bloquée : '.$tenant->name, "{$what} de {$tenant->name} a pris fin le {$date} et le délai de grâce est écoulé : seul l’administrateur peut se connecter, pour payer.", $link, 'error'),
            Tenant::STATE_GRACE => $alerts->once($tenant, 'grace:'.$end->toDateString(), 'subscription.grace',
                'Délai de grâce : '.$tenant->name, "{$what} de {$tenant->name} a pris fin le {$date}. L’école sera bloquée le ".$tenant->graceEndsAt()?->format('d/m/Y').' sans paiement.', $link, 'warning'),
            Tenant::STATE_TRIAL, Tenant::STATE_ACTIVE => $this->daysLeft($tenant) <= max(config('ardoise.billing.reminder_days') ?: [7])
                ? $alerts->once($tenant, 'ending:'.$end->toDateString(), 'subscription.ending',
                    'Échéance proche : '.$tenant->name, "{$what} de {$tenant->name} se termine le {$date} (dans {$this->daysLeft($tenant)} jour(s)).", $link)
                : false,
            default => false,
        };
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
