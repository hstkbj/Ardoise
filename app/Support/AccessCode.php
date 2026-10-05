<?php

namespace App\Support;

/**
 * Codes d'accès parents : « K7QM-4XPA-9R2T ».
 * 12 caractères sur un alphabet de 31 symboles ≈ 59 bits d'entropie.
 */
final class AccessCode
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 12, string $alphabet = self::ALPHABET): string
    {
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /** Saisie libre → forme canonique (majuscules, sans espaces ni tirets). */
    public static function normalize(string $input): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';
    }

    public static function format(string $code, int $group = 4): string
    {
        return implode('-', str_split(self::normalize($code), $group));
    }

    /** Empreinte stockée en base centrale (le code en clair n'y figure pas). */
    public static function hash(string $code, string $key): string
    {
        return hash_hmac('sha256', self::normalize($code), $key);
    }

    public static function isWellFormed(string $input, int $length = 12): bool
    {
        $code = self::normalize($input);

        return strlen($code) === $length && strspn($code, self::ALPHABET) === $length;
    }
}
