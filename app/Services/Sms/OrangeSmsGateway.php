<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Orange SMS API (developer.orange.com — Côte d'Ivoire, Sénégal, Mali, Cameroun, Guinée…).
 *
 *   SMS_DRIVER=orange
 *   SMS_ORANGE_CLIENT_ID=…  SMS_ORANGE_CLIENT_SECRET=…   (application Orange Developer)
 *   SMS_ORANGE_SENDER_NUMBER=+2250000000000              (numéro du contrat, au format international)
 *   SMS_SENDER=ARDOISE                                   (nom d'expéditeur validé par Orange, facultatif)
 */
class OrangeSmsGateway extends AbstractHttpSmsGateway
{
    protected const BASE_URL = 'https://api.orange.com';

    protected function requiredConfig(): array
    {
        return ['client_id', 'client_secret', 'sender_number'];
    }

    protected function deliver(string $to, string $message): bool
    {
        $sender = 'tel:+'.ltrim((string) $this->config['sender_number'], '+');
        $body = ['outboundSMSMessageRequest' => array_filter([
            'address' => 'tel:'.$to,
            'senderAddress' => $sender,
            'senderName' => $this->config['sender_name'] ?? $this->sender,
            'outboundSMSTextMessage' => ['message' => $message],
        ])];

        $response = Http::timeout(15)->withToken($this->accessToken())->acceptJson()
            ->post(self::BASE_URL.'/smsmessaging/v1/outbound/'.rawurlencode($sender).'/requests', $body);

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        return $response->successful();
    }

    /** Jeton OAuth2 (client_credentials), mis en cache jusqu'à son expiration. */
    protected function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function () {
            $response = Http::timeout(15)->asForm()->acceptJson()
                ->withBasicAuth((string) $this->config['client_id'], (string) $this->config['client_secret'])
                ->post(self::BASE_URL.'/oauth/v3/token', ['grant_type' => 'client_credentials']);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Orange SMS : authentification refusée ('.$response->status().').');
            }

            return (string) $response->json('access_token');
        });
    }

    protected function tokenCacheKey(): string
    {
        return 'sms:orange:token:'.md5((string) $this->config['client_id']);
    }
}
