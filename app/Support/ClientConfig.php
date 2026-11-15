<?php

namespace App\Support;

/**
 * Réglages publics transmis à la page (window.__ARDOISE__).
 * Uniquement des valeurs que n'importe quel visiteur peut voir : jamais de clé secrète.
 */
class ClientConfig
{
    public const ANALYTICS_PROVIDERS = ['plausible', 'umami', 'ga4'];

    /**
     * Premiers segments d'adresse gérés par le routeur Vue (resources/js/router/index.js).
     * Une adresse hors de cette liste reçoit le statut HTTP 404 (la page 404 de l'application s'affiche).
     */
    public const SPA_SEGMENTS = [
        '', 'features', 'pricing', 'contact', 'guide', 'privacy', 'terms',
        'login', 'forgot-password', 'reset-password',
        'admin', 'teacher', 'superadmin', 'profile', 'security', 'parent',
    ];

    public static function isKnownPath(string $path): bool
    {
        return in_array(explode('/', trim($path, '/'))[0], self::SPA_SEGMENTS, true);
    }

    /** @return array<string, mixed> */
    public static function for(bool $central): array
    {
        return [
            'central' => $central,
            'legal' => array_filter((array) config('ardoise.legal'), fn ($value) => filled($value)),
            'seo' => ['pages' => config('ardoise.seo.pages'), 'siteName' => config('ardoise.seo.site_name')],
            'turnstileSiteKey' => SpamGuard::enabled() ? SpamGuard::turnstileSiteKey() : null,
            'analytics' => $central ? self::analytics() : null,
        ];
    }

    /** @return array{provider: string, id: string, src: string|null}|null */
    private static function analytics(): ?array
    {
        $provider = strtolower((string) config('ardoise.analytics.provider'));
        $id = (string) config('ardoise.analytics.id');

        if (! in_array($provider, self::ANALYTICS_PROVIDERS, true) || $id === '') {
            return null;
        }

        $src = (string) config('ardoise.analytics.src');

        return ['provider' => $provider, 'id' => $id, 'src' => str_starts_with($src, 'https://') ? $src : null];
    }
}
