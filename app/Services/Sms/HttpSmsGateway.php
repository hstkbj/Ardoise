<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fournisseur générique HTTP (JSON { to, from, message }, jeton Bearer).
 * À adapter au fournisseur retenu (Orange SMS API, Twilio, Africa's Talking…).
 */
class HttpSmsGateway implements SmsGateway
{
    public function __construct(protected array $config) {}

    public function send(string $to, string $message): bool
    {
        if (empty($this->config['url'])) {
            Log::warning('[SMS] SMS_HTTP_URL non configuré, message non envoyé.');

            return false;
        }

        try {
            return Http::withToken((string) $this->config['token'])
                ->timeout(10)
                ->post($this->config['url'], ['to' => $to, 'from' => config('ardoise.sms.sender'), 'message' => $message])
                ->successful();
        } catch (Throwable $e) {
            Log::error('[SMS] échec : '.$e->getMessage());

            return false;
        }
    }
}
