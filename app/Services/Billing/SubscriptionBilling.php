<?php

namespace App\Services\Billing;

use App\Mail\SubscriptionPaidMail;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Services\Payments\FedaPay\FedaPayClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Abonnements des écoles : état, paiement en ligne (FedaPay, compte de la
 * plateforme) et prolongation après paiement confirmé.
 */
class SubscriptionBilling
{
    public function __construct(protected FedaPayClient $fedapay) {}

    /**
     * État de l'abonnement, tel que l'affiche l'interface.
     *
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');
        $end = $tenant->billingEndsAt();

        return [
            'state' => $tenant->billingState(),
            'requires_payment' => $tenant->requiresPayment(),
            'is_trial' => $tenant->status === 'trial',
            'ends_at' => $end?->toIso8601String(),
            'grace_ends_at' => $tenant->graceEndsAt()?->toIso8601String(),
            'days_left' => $end ? (int) floor(now()->diffInDays($end, false)) : null,
            'plan' => $tenant->plan ? [
                'id' => $tenant->plan->id,
                'name' => $tenant->plan->name,
                'price' => $tenant->plan->price,
                'period' => $tenant->plan->period,
            ] : null,
            'features' => $tenant->features(),
            'limits' => $tenant->limits(),
        ];
    }

    /**
     * Montant et durée pour un nombre de périodes du plan.
     *
     * @return array{amount: int, months: int}
     */
    public function quote(Plan $plan, int $periods): array
    {
        if ($plan->price === null || $plan->price <= 0) {
            throw ValidationException::withMessages(['plan_id' => 'Ce plan est sur devis : contactez-nous pour l’activer.']);
        }

        if ($periods < 1 || $periods > config('ardoise.billing.max_periods')) {
            throw ValidationException::withMessages(['periods' => 'Durée invalide.']);
        }

        return [
            'amount' => $plan->price * $periods,
            'months' => ($plan->period === 'yearly' ? 12 : 1) * $periods,
        ];
    }

    /**
     * Crée le paiement en attente et la transaction FedaPay ; renvoie l'URL de paiement.
     *
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $payer
     */
    public function startCheckout(Tenant $tenant, Plan $plan, int $periods, array $payer = []): SubscriptionPayment
    {
        if (! $this->fedapay->isConfigured()) {
            throw ValidationException::withMessages(['plan_id' => 'Le paiement en ligne n’est pas encore disponible. Contactez la plateforme.']);
        }

        ['amount' => $amount, 'months' => $months] = $this->quote($plan, $periods);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $tenant->subscription?->id,
            'plan_id' => $plan->id,
            'customer' => $payer['name'] ?? $tenant->admin_name,
            'amount' => $amount,
            'months' => $months,
            'method' => 'FedaPay',
            'provider' => 'fedapay',
            'reference' => $this->nextReference(),
            'status' => 'pending',
        ]);

        [$firstname, $lastname] = $this->splitName($payer['name'] ?? $tenant->admin_name);

        try {
            $checkout = $this->fedapay->checkout(
                amount: $amount,
                description: 'Abonnement '.config('app.name').' — '.$plan->name.' — '.$tenant->name,
                callbackUrl: $tenant->url('/admin/billing?payment='.$payment->reference),
                merchantReference: $payment->reference,
                customer: ['firstname' => $firstname, 'lastname' => $lastname, 'email' => $payer['email'] ?? $tenant->admin_email, 'phone' => $payer['phone'] ?? null],
                metadata: ['type' => 'subscription', 'tenant' => $tenant->code],
            );
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed', 'meta' => ['error' => $e->getMessage()]]);
            Log::warning('FedaPay : création de transaction impossible', ['tenant' => $tenant->code, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['plan_id' => 'Le service de paiement est indisponible. Réessayez dans quelques minutes.']);
        }

        $payment->update(['provider_reference' => $checkout['id'], 'meta' => ['checkout_url' => $checkout['url']]]);

        return $payment;
    }

    /** Interroge FedaPay et applique le résultat (idempotent). */
    public function syncFromProvider(SubscriptionPayment $payment): SubscriptionPayment
    {
        if ($payment->status !== 'pending' || $payment->provider !== 'fedapay' || ! $payment->provider_reference) {
            return $payment;
        }

        $transaction = $this->fedapay->retrieve($payment->provider_reference);
        $status = (string) ($transaction['status'] ?? 'pending');

        if (in_array($status, FedaPayClient::PAID_STATUSES, true)) {
            if ((int) ($transaction['amount'] ?? 0) < $payment->amount) {
                Log::error('FedaPay : montant payé inférieur au montant attendu', ['reference' => $payment->reference]);

                return $payment;
            }

            return $this->markPaid($payment, isset($transaction['approved_at']) ? Carbon::parse($transaction['approved_at']) : null);
        }

        if (in_array($status, FedaPayClient::FAILED_STATUSES, true)) {
            $payment->update(['status' => 'failed', 'meta' => array_merge($payment->meta ?? [], ['provider_status' => $status])]);
        }

        return $payment->refresh();
    }

    /**
     * Paiement confirmé : prolonge l'abonnement à partir de la date de fin
     * en cours (si elle est future) ou d'aujourd'hui, et active le plan payé.
     */
    public function markPaid(SubscriptionPayment $payment, ?Carbon $paidAt = null): SubscriptionPayment
    {
        $applied = DB::connection('central')->transaction(function () use ($payment, $paidAt) {
            $payment = SubscriptionPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                return false;
            }

            $tenant = Tenant::lockForUpdate()->findOrFail($payment->tenant_id);
            $this->extend($tenant, $payment->months ?? 0, $payment->plan_id, $payment->amount);
            $payment->update(['status' => 'paid', 'paid_at' => $paidAt ?? now(), 'subscription_id' => $tenant->subscription?->id]);

            return true;
        });

        $payment->refresh();

        if ($applied && $payment->tenant->admin_email) {
            Mail::to($payment->tenant->admin_email)->queue(new SubscriptionPaidMail($payment));
        }

        return $payment;
    }

    /** Prolonge l'abonnement de N mois (paiement en ligne ou saisi par la plateforme). */
    public function extend(Tenant $tenant, int $months, ?int $planId = null, ?int $amount = null): void
    {
        $currentEnd = $tenant->billingEndsAt();
        $from = $currentEnd && $currentEnd->isFuture() ? $currentEnd : now();
        $expiresAt = $months > 0 ? $from->copy()->addMonths($months) : $from;

        $tenant->update([
            'status' => 'active',
            'plan_id' => $planId ?? $tenant->plan_id,
            'expires_at' => $expiresAt,
            'trial_ends_at' => null,
        ]);

        $subscription = $tenant->subscription()->first();
        $values = ['plan_id' => $tenant->plan_id, 'status' => 'active', 'expires_at' => $expiresAt, 'amount' => $amount ?? $subscription?->amount];

        $subscription
            ? $subscription->update($values)
            : Subscription::create($values + ['tenant_id' => $tenant->id, 'starts_at' => now()]);

        $tenant->unsetRelation('subscription');
    }

    public function nextReference(): string
    {
        do {
            $reference = 'SUB-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
        } while (SubscriptionPayment::where('reference', $reference)->exists());

        return $reference;
    }

    /** @return array{0: string|null, 1: string|null} */
    protected function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? ($parts[0] ?? null)];
    }
}
