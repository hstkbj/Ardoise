<?php

namespace App\Support;

final class Phone
{
    /** « +225 07 48 21 33 90 » → « +2250748213390 » */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return (str_starts_with(trim($phone), '+') ? '+' : '').$digits;
    }

    public static function looksLikePhone(string $value): bool
    {
        return ! str_contains($value, '@') && preg_match('/^\+?[\d\s.\-()]{6,}$/', $value) === 1;
    }
}
