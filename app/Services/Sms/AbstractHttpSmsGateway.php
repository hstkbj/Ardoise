<?php

namespace App\Services\Sms;

use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Base des fournisseurs HTTP : numéro au format international, erreurs journalisées. */
abstract class AbstractHttpSmsGateway implements SmsGateway
{
    /** @param  array<string, mixed>  $config */
    public function __construct(protected array $config, protected string $sender = 'ARDOISE') {}

    /** Envoi effectif ; renvoie vrai si le fournisseur a accepté le message. */
    abstract protected function deliver(string $to, string $message): bool;

    /** @return array<int, string> clés de configuration obligatoires */
    abstract protected function requiredConfig(): array;

    public function send(string $to, string $message): bool
    {
        $missing = array_filter($this->requiredConfig(), fn ($key) => blank($this->config[$key] ?? null));

        if ($missing) {
            Log::warning('[SMS] '.static::class.' : configuration incomplète ('.implode(', ', $missing).'), message non envoyé.');

            return false;
        }

        try {
            $delivered = $this->deliver($this->international($to), $message);

            if (! $delivered) {
                Log::warning('[SMS] message refusé par le fournisseur', ['to' => $this->mask($to), 'gateway' => class_basename(static::class)]);
            }

            return $delivered;
        } catch (Throwable $e) {
            Log::error('[SMS] échec : '.$e->getMessage(), ['to' => $this->mask($to), 'gateway' => class_basename(static::class)]);

            return false;
        }
    }

    /** « 07 48 21 33 90 » → « +2250748213390 » avec l'indicatif par défaut (SMS_DEFAULT_COUNTRY_CODE). */
    protected function international(string $phone): string
    {
        $normalized = (string) Phone::normalize($phone);

        if (str_starts_with($normalized, '+')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '00')) {
            return '+'.substr($normalized, 2);
        }

        $country = ltrim((string) config('ardoise.sms.default_country_code'), '+');

        return $country !== '' ? '+'.$country.$normalized : $normalized;
    }

    protected function mask(string $phone): string
    {
        return substr($phone, 0, 4).'…'.substr($phone, -2);
    }
}
