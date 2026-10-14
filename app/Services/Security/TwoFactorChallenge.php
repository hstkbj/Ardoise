<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Étape intermédiaire de connexion : mot de passe vérifié, code à 6 chiffres
 * attendu. Le jeton remis au client est valable 10 minutes et 5 essais.
 */
class TwoFactorChallenge
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** @param  array<string, mixed>  $payload */
    public function create(array $payload): string
    {
        $token = Str::random(64);
        Cache::put($this->key($token), $payload + ['attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function find(string $token): ?array
    {
        return Cache::get($this->key($token));
    }

    /** Code faux : compte l'essai ; le défi est détruit après 5 échecs. */
    public function fail(string $token): void
    {
        $payload = $this->find($token);

        if (! $payload) {
            return;
        }

        if (++$payload['attempts'] >= self::MAX_ATTEMPTS) {
            $this->forget($token);

            return;
        }

        Cache::put($this->key($token), $payload, now()->addMinutes(self::TTL_MINUTES));
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    protected function key(string $token): string
    {
        return '2fa-challenge:'.hash('sha256', $token);
    }
}
