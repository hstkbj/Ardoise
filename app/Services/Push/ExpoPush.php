<?php

namespace App\Services\Push;

use App\Models\Tenant\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi des notifications push par le service d'Expo (https://docs.expo.dev/push-notifications/sending-notifications/).
 * Expo relaie vers Firebase (Android) et Apple (iOS) : le téléphone affiche la notification même application fermée.
 *
 * Un téléphone désinstallé ou déconnecté renvoie « DeviceNotRegistered » : son jeton est supprimé.
 */
class ExpoPush
{
    /** Expo accepte 100 messages par requête. */
    protected const CHUNK = 100;

    /**
     * @param  list<string>  $tokens
     * @param  array{title: string, body: string, data?: array<string, mixed>}  $message
     * @return int nombre de messages acceptés par Expo
     */
    public function send(array $tokens, array $message): int
    {
        $tokens = array_values(array_unique(array_filter($tokens, fn ($t) => is_string($t) && preg_match(PushToken::PATTERN, $t))));
        $accepted = 0;

        foreach (array_chunk($tokens, self::CHUNK) as $chunk) {
            $payload = array_map(fn (string $token) => [
                'to' => $token,
                'title' => $message['title'],
                'body' => $message['body'],
                'data' => (object) ($message['data'] ?? []),
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
            ], $chunk);

            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(15)
                    ->when(config('services.expo.access_token'), fn ($http, $token) => $http->withToken($token))
                    ->post(config('services.expo.push_url'), $payload);
            } catch (Throwable $e) {
                Log::warning('Push Expo injoignable : '.$e->getMessage(), ['tenant' => tenant()?->code]);

                continue;
            }

            if (! $response->successful()) {
                Log::warning('Push Expo refusé ('.$response->status().')', ['body' => mb_substr($response->body(), 0, 500), 'tenant' => tenant()?->code]);

                continue;
            }

            $accepted += $this->handleTickets($chunk, (array) $response->json('data', []));
        }

        if ($tokens !== []) {
            PushToken::whereIn('token', $tokens)->update(['last_used_at' => now()]);
        }

        return $accepted;
    }

    /**
     * Un ticket par message, dans le même ordre.
     *
     * @param  list<string>  $tokens
     * @param  array<int, mixed>  $tickets
     */
    protected function handleTickets(array $tokens, array $tickets): int
    {
        $accepted = 0;
        $gone = [];

        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) === 'ok') {
                $accepted++;
            } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($tokens[$i])) {
                $gone[] = $tokens[$i];
            } else {
                Log::info('Push Expo non délivré : '.($ticket['message'] ?? 'erreur inconnue'), ['tenant' => tenant()?->code]);
            }
        }

        if ($gone !== []) {
            PushToken::whereIn('token', $gone)->delete();
        }

        return $accepted;
    }
}
