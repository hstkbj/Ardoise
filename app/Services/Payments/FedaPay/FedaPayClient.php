<?php

namespace App\Services\Payments\FedaPay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client minimal de l'API REST FedaPay (v1), sans SDK.
 *
 *  - POST /transactions          crée une transaction (statut « pending »)
 *  - POST /transactions/{id}/token  renvoie { token, url } : page de paiement
 *  - GET  /transactions/{id}     statut à jour (approved, declined, canceled…)
 *
 * Un même client sert le compte de la plateforme (abonnements) et le compte
 * d'une école (frais scolaires) : seules les clés changent.
 */
class FedaPayClient
{
    /** Statuts FedaPay considérés comme payés. */
    public const PAID_STATUSES = ['approved', 'transferred'];

    /** Statuts définitivement non payés. */
    public const FAILED_STATUSES = ['declined', 'canceled', 'cancelled', 'expired', 'refunded'];

    public function __construct(
        protected ?string $secretKey,
        protected string $environment = 'sandbox',
        protected string $currency = 'XOF',
        protected string $country = 'bj',
        protected int $timeout = 20,
    ) {}

    /** Client du compte FedaPay de la plateforme (clés du .env). */
    public static function platform(): self
    {
        $config = config('ardoise.fedapay');

        return new self($config['secret_key'], $config['environment'], $config['currency'], $config['country'], $config['timeout']);
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey);
    }

    public function baseUrl(): string
    {
        return $this->environment === 'live' ? 'https://api.fedapay.com/v1' : 'https://sandbox-api.fedapay.com/v1';
    }

    /**
     * Crée une transaction puis son lien de paiement.
     *
     * @param  array{firstname?: string|null, lastname?: string|null, email?: string|null, phone?: string|null}  $customer
     * @param  array<string, scalar>  $metadata
     * @return array{id: string, url: string, token: string|null, status: string}
     */
    public function checkout(int $amount, string $description, string $callbackUrl, string $merchantReference, array $customer = [], array $metadata = []): array
    {
        $transaction = $this->createTransaction([
            'description' => mb_substr($description, 0, 250),
            'amount' => $amount,
            'currency' => ['iso' => $this->currency],
            'callback_url' => $callbackUrl,
            'merchant_reference' => $merchantReference,
            'custom_metadata' => $metadata ?: null,
            'customer' => $this->customer($customer),
        ]);

        $token = $this->request()->post('/transactions/'.$transaction['id'].'/token');
        $this->ensureSuccessful($token);

        return [
            'id' => (string) $transaction['id'],
            'url' => (string) $token->json('url'),
            'token' => $token->json('token'),
            'status' => (string) ($transaction['status'] ?? 'pending'),
        ];
    }

    /** @return array<string, mixed> */
    public function createTransaction(array $payload): array
    {
        $response = $this->request()->post('/transactions', array_filter($payload, fn ($value) => $value !== null));
        $this->ensureSuccessful($response);

        return $this->unwrap($response->json());
    }

    /** @return array<string, mixed> transaction à jour (id, status, amount, merchant_reference…) */
    public function retrieve(string|int $transactionId): array
    {
        $response = $this->request()->get('/transactions/'.$transactionId);
        $this->ensureSuccessful($response);

        return $this->unwrap($response->json());
    }

    /**
     * Vérifie l'en-tête X-FEDAPAY-SIGNATURE : « t=<horodatage>,s=<hmac> »,
     * HMAC-SHA256 de « <horodatage>.<corps brut> » avec le secret du webhook.
     */
    public static function verifySignature(string $payload, ?string $header, ?string $secret, int $tolerance = 300): bool
    {
        if (blank($header) || blank($secret)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);

            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 's' && $value) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new FedaPayException('Le paiement en ligne FedaPay n’est pas configuré.');
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    protected function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error') ?? 'Erreur FedaPay ('.$response->status().').';

        if (is_array($message)) {
            $message = json_encode($message);
        }

        throw new FedaPayException((string) $message, $response->status());
    }

    /**
     * Les réponses FedaPay enveloppent l'objet : { "v1/transaction": { … } }.
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    protected function unwrap(?array $json): array
    {
        $json ??= [];

        foreach (['v1/transaction', 'transaction'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                return $json[$key];
            }
        }

        return $json;
    }

    /**
     * @param  array{firstname?: string|null, lastname?: string|null, email?: string|null, phone?: string|null}  $customer
     * @return array<string, mixed>|null
     */
    protected function customer(array $customer): ?array
    {
        $data = array_filter([
            'firstname' => $customer['firstname'] ?? null,
            'lastname' => $customer['lastname'] ?? null,
            'email' => $customer['email'] ?? null,
        ]);

        $phone = preg_replace('/\D+/', '', (string) ($customer['phone'] ?? ''));

        if ($phone !== '') {
            $data['phone_number'] = ['number' => $phone, 'country' => $this->country];
        }

        return $data ?: null;
    }
}
