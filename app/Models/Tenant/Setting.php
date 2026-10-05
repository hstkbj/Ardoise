<?php

namespace App\Models\Tenant;

use App\Support\SettingDefaults;

/** Paramètres de l'école, une ligne par section (identity, grading, finance…). */
class Setting extends TenantModel
{
    protected static array $memo = [];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public static function section(string $section): array
    {
        $key = (tenant()?->id ?? 0).':'.$section;

        return static::$memo[$key] ??= array_merge(
            SettingDefaults::for($section),
            static::where('section', $section)->first()?->values ?? [],
        );
    }

    public static function get(string $section, string $key, mixed $default = null): mixed
    {
        return static::section($section)[$key] ?? $default;
    }

    public static function put(string $section, array $values): array
    {
        $merged = array_merge(static::section($section), $values);
        static::updateOrCreate(['section' => $section], ['values' => $merged]);
        static::$memo = [];

        return $merged;
    }
}
