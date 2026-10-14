<?php

namespace App\Support\Spreadsheet;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/** Conversion des valeurs saisies dans un tableur (dates françaises, oui/non, montants). */
final class CellParser
{
    /** 15/09/2012, 15-09-2012, 2012-09-15 ou numéro de série Excel → « 2012-09-15 » ; false si invalide. */
    public static function date(?string $value): string|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 1000 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);

                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // format suivant
            }
        }

        return false;
    }

    public static function boolean(?string $value): bool
    {
        return in_array(Str::lower(Str::ascii(trim((string) $value))), ['1', 'oui', 'o', 'x', 'yes', 'y', 'vrai', 'true', 'abs', 'absent', 'absente'], true);
    }

    /** « 50 000 », « 50.000 », « 50000 FCFA » → 50000 ; null si vide ; false si invalide. */
    public static function amount(?string $value): int|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/[\s\x{00A0}\x{202F}.]|fcfa|xof|f$/iu', '', $value);
        $digits = str_replace(',', '.', $digits);

        return is_numeric($digits) && (float) $digits >= 0 ? (int) round((float) $digits) : false;
    }

    /** F, M ; accepte Féminin, Fille, Masculin, Garçon, H. */
    public static function gender(?string $value): string|false|null
    {
        $value = Str::lower(Str::ascii(trim((string) $value)));

        return match (true) {
            $value === '' => null,
            in_array($value, ['f', 'feminin', 'fille', 'femme'], true) => 'F',
            in_array($value, ['m', 'h', 'masculin', 'garcon', 'homme'], true) => 'M',
            default => false,
        };
    }

    /** Première valeur non vide parmi plusieurs en-têtes possibles. */
    public static function pick(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim($row[$key]) !== '') {
                return trim($row[$key]);
            }
        }

        return '';
    }
}
