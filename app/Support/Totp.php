<?php

namespace App\Support;

/**
 * Codes à usage unique basés sur le temps (RFC 6238), compatibles avec
 * Google Authenticator, Microsoft Authenticator, Authy, 2FAS…
 * Secret encodé en Base32, codes à 6 chiffres, pas de 30 secondes, HMAC-SHA1.
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Secret aléatoire de 160 bits, encodé en Base32 (32 caractères). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Code attendu pour un pas de temps donné (par défaut : maintenant). */
    public static function code(string $secret, ?int $timestep = null): string
    {
        $timestep ??= self::timestep();
        $hash = hash_hmac('sha1', pack('N*', 0, $timestep), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Pas de temps correspondant au code saisi (tolérance d'un pas avant/après
     * pour le décalage d'horloge du téléphone), ou null si le code est faux.
     */
    public static function verify(string $secret, string $code, int $window = 1): ?int
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== self::DIGITS) {
            return null;
        }

        $now = self::timestep();

        for ($step = $now - $window; $step <= $now + $window; $step++) {
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** URI otpauth:// à encoder dans le QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function timestep(): int
    {
        return intdiv(now()->getTimestamp(), self::PERIOD);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=]/', '', $secret));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index !== false) {
                $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $out = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
