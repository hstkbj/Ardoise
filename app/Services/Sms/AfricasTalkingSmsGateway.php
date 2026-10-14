<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Africa's Talking (SMS bulk).
 *
 *   SMS_DRIVER=africastalking
 *   SMS_AT_USERNAME=…  SMS_AT_API_KEY=…
 *   SMS_AT_FROM=ARDOISE      (short code ou nom d'expéditeur enregistré, facultatif)
 *   SMS_AT_SANDBOX=false
 */
class AfricasTalkingSmsGateway extends AbstractHttpSmsGateway
{
    protected function requiredConfig(): array
    {
        return ['username', 'api_key'];
    }

    protected function deliver(string $to, string $message): bool
    {
        $host = ($this->config['sandbox'] ?? false) ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';

        $response = Http::timeout(15)->asForm()->acceptJson()
            ->withHeaders(['apiKey' => (string) $this->config['api_key']])
            ->post($host.'/version1/messaging', array_filter([
                'username' => $this->config['username'],
                'to' => $to,
                'message' => $message,
                'from' => $this->config['from'] ?? null,
            ]));

        $status = $response->json('SMSMessageData.Recipients.0.status');

        return $response->successful() && in_array($status, ['Success', 'Sent', 'Queued'], true);
    }
}
