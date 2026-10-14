<?php

namespace App\Models\Concerns;

use App\Support\Totp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Double authentification (application TOTP + codes de secours).
 * Colonnes : two_factor_secret, two_factor_recovery_codes (chiffrés), two_factor_confirmed_at.
 */
trait HasTwoFactorAuthentication
{
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** Nouveau secret, actif seulement après confirmation d'un premier code. */
    public function startTwoFactorSetup(): string
    {
        $secret = Totp::generateSecret();
        $this->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();

        return $secret;
    }

    /** @return array<int, string> codes de secours en clair (affichés une seule fois) */
    public function confirmTwoFactor(): array
    {
        $codes = $this->newRecoveryCodes();
        $this->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(): array
    {
        $codes = $this->newRecoveryCodes();
        $this->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    }

    /** Code de l'application (refusé s'il a déjà servi) : protège contre le rejeu. */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (! filled($this->two_factor_secret)) {
            return false;
        }

        $step = Totp::verify($this->two_factor_secret, $code);

        if ($step === null) {
            return false;
        }

        return Cache::add('2fa-used:'.static::class.':'.$this->getConnectionName().':'.$this->getKey().':'.$step, true, now()->addMinutes(5));
    }

    /** Code de secours : utilisable une seule fois. */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $codes = $this->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if ($code !== '' && hash_equals(str_replace('-', '', $stored), $code)) {
                unset($codes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** 2e étape de connexion : code de l'application, ou code de secours. */
    public function passesTwoFactorChallenge(?string $code, ?string $recoveryCode = null): bool
    {
        if (filled($recoveryCode)) {
            return $this->useRecoveryCode($recoveryCode);
        }

        return filled($code) && $this->verifyTwoFactorCode($code);
    }

    public function remainingRecoveryCodes(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /** @return array<int, string> 8 codes « ABCDE-FGHIJ » */
    protected function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }
}
