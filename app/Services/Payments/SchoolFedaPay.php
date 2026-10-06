<?php

namespace App\Services\Payments;

use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use App\Services\Notifier;
use App\Services\Payments\FedaPay\FedaPayClient;
use App\Services\PaymentService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paiement des frais scolaires par les parents, sur le compte FedaPay de l'école.
 *
 * Les clés sont saisies par l'école (Paramètres → Paiement en ligne) et
 * stockées chiffrées dans sa base ; l'argent arrive directement sur son compte.
 */
class SchoolFedaPay
{
    public const SECTION = 'online_payments';

    /** Champs secrets : chiffrés en base, jamais renvoyés à l'interface. */
    public const SECRET_FIELDS = ['secret_key', 'webhook_secret'];

    public function __construct(protected PaymentService $payments, protected Notifier $notifier) {}

    /** @return array{enabled: string, environment: string, public_key: string|null, secret_key: string|null, webhook_secret: string|null} */
    public function settings(): array
    {
        $values = Setting::section(self::SECTION);

        foreach (self::SECRET_FIELDS as $field) {
            $values[$field] = $this->decrypt($values[$field] ?? null);
        }

        return $values;
    }

    /** Valeurs à enregistrer : secrets chiffrés ; un secret laissé vide conserve l'ancien. */
    public static function prepareForStorage(array $input): array
    {
        $current = Setting::section(self::SECTION);

        foreach (self::SECRET_FIELDS as $field) {
            $input[$field] = filled($input[$field] ?? null)
                ? Crypt::encryptString(trim($input[$field]))
                : ($current[$field] ?? null);
        }

        return $input;
    }

    /** Valeurs affichables : les secrets sont remplacés par un indicateur. */
    public static function forDisplay(array $values): array
    {
        foreach (self::SECRET_FIELDS as $field) {
            $values[$field.'_set'] = filled($values[$field] ?? null);
            $values[$field] = null;
        }

        $values['webhook_url'] = rtrim((string) config('app.url'), '/').'/api/v1/webhooks/fedapay/'.tenant()->code;

        return $values;
    }

    /** Le parent peut payer en ligne : module du plan + clés renseignées + activé par l'école. */
    public function isEnabled(): bool
    {
        $settings = $this->settings();

        return (bool) tenant()?->hasFeature('online_payments')
            && ($settings['enabled'] ?? 'no') === 'yes'
            && filled($settings['secret_key']);
    }

    public function client(): FedaPayClient
    {
        $settings = $this->settings();

        return new FedaPayClient(
            $settings['secret_key'],
            $settings['environment'] === 'live' ? 'live' : 'sandbox',
            config('ardoise.fedapay.currency'),
            config('ardoise.fedapay.country'),
            config('ardoise.fedapay.timeout'),
        );
    }

    public function webhookSecret(): ?string
    {
        return $this->settings()['webhook_secret'];
    }

    /**
     * Crée le paiement « en attente » et la transaction FedaPay de l'école.
     *
     * @return array{payment: Payment, url: string}
     */
    public function startCheckout(FeeAssignment $assignment, int $amount, User $payer, ?string $phone = null): array
    {
        $assignment->loadMissing('fee', 'student');
        $payment = $this->payments->createPending($assignment, $amount, 'FedaPay', null);
        [$firstname, $lastname] = array_pad(preg_split('/\s+/', trim($payer->name), 2) ?: [], 2, null);

        try {
            $checkout = $this->client()->checkout(
                amount: $amount,
                description: $assignment->fee->name.' — '.$assignment->student->full_name,
                callbackUrl: tenant()->url('/parent/payments?payment='.$payment->reference),
                merchantReference: tenant()->code.'-'.$payment->reference,
                customer: ['firstname' => $firstname, 'lastname' => $lastname ?? $firstname, 'email' => $payer->email, 'phone' => $phone ?? $payer->routeNotificationForSms()],
                metadata: ['type' => 'school_fee', 'tenant' => tenant()->code, 'payment' => $payment->reference],
            );
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed', 'note' => 'FedaPay indisponible']);
            Log::warning('FedaPay école : transaction impossible', ['tenant' => tenant()->code, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['method' => 'Le paiement en ligne est momentanément indisponible. Réessayez plus tard.']);
        }

        $payment->update(['transaction_ref' => $checkout['id']]);

        return ['payment' => $payment->refresh(), 'url' => $checkout['url']];
    }

    /** Interroge FedaPay et applique le résultat au paiement (idempotent). */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status !== 'pending' || $payment->method !== 'FedaPay' || ! $payment->transaction_ref) {
            return $payment;
        }

        $transaction = $this->client()->retrieve($payment->transaction_ref);
        $status = (string) ($transaction['status'] ?? 'pending');

        if (in_array($status, FedaPayClient::PAID_STATUSES, true) && (int) ($transaction['amount'] ?? 0) >= $payment->amount) {
            $payment = $this->payments->confirm($payment);
            $payment->loadMissing('student', 'assignment.fee');
            $amount = number_format($payment->amount, 0, ',', ' ');
            $fee = $payment->assignment?->fee?->name;

            $this->notifier->event('payment.received', 'Paiement reçu', "{$amount} FCFA reçus pour {$fee}. Merci.", $payment->student, ['parent' => '/parent/payments', 'staff' => '/admin/payments/'.$payment->fee_assignment_id]);
            $this->notifier->event('payment.online_received', 'Paiement en ligne reçu', $payment->student?->full_name." · {$amount} FCFA ({$fee})", $payment->student, ['staff' => '/admin/payments/'.$payment->fee_assignment_id]);
        } elseif (in_array($status, FedaPayClient::FAILED_STATUSES, true)) {
            $payment->update(['status' => 'failed', 'note' => 'FedaPay : '.$status]);
        }

        return $payment->refresh();
    }

    protected function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
