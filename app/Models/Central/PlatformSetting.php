<?php

namespace App\Models\Central;

class PlatformSetting extends CentralModel
{
    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    /** @return array<string, mixed> */
    public static function section(string $section): array
    {
        return static::where('section', $section)->first()?->values ?? [];
    }

    /** Réglage oui / non (« yes », « 1 », « true », « oui »). */
    public static function flag(string $section, string $key, bool $default = false): bool
    {
        $value = static::section($section)[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) (is_bool($value) ? (int) $value : $value)), ['1', 'yes', 'true', 'oui', 'on'], true);
    }

    public static function value(string $section, string $key, mixed $default = null): mixed
    {
        $value = static::section($section)[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }
}
