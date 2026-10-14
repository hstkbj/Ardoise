<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Twilio Programmable Messaging.
 *
 *   SMS_DRIVER=twilio
 *   SMS_TWILIO_SID=AC…  SMS_TWILIO_TOKEN=…
 *   SMS_TWILIO_FROM=+1…  (numéro Twilio) ou SMS_TWILIO_MESSAGING_SERVICE=MG…
 */
class TwilioSmsGateway extends AbstractHttpSmsGateway
{
    protected function requiredConfig(): array
    {
        return ['sid', 'token'];
    }

    protected function deliver(string $to, string $message): bool
    {
        $payload = ['To' => $to, 'Body' => $message];

        if (filled($this->config['messaging_service'] ?? null)) {
            $payload['MessagingServiceSid'] = $this->config['messaging_service'];
        } else {
            $payload['From'] = $this->config['from'] ?: $this->sender;
        }

        return Http::timeout(15)->asForm()->acceptJson()
            ->withBasicAuth((string) $this->config['sid'], (string) $this->config['token'])
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$this->config['sid'].'/Messages.json', $payload)
            ->successful();
    }
}
