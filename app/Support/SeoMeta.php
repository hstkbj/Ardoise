<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Balises meta (titre, description, Open Graph, Twitter) de la page demandée.
 * Les textes viennent de config('ardoise.seo.pages') ; toute autre adresse
 * (connexion, espaces de l'école, pages inconnues) reçoit « noindex ».
 */
class SeoMeta
{
    /**
     * @return array{title: string, description: string, url: string, image: string, image_alt: string, site_name: string, robots: string}
     */
    public static function forRequest(Request $request, bool $centralHost): array
    {
        $config = (array) config('ardoise.seo');
        $path = '/'.trim($request->path(), '/');
        $page = $centralHost ? ($config['pages'][$path] ?? null) : null;
        $fallback = $config['pages']['/'];

        return [
            'title' => $page['title'] ?? $fallback['title'],
            'description' => $page['description'] ?? $fallback['description'],
            'url' => $request->getSchemeAndHttpHost().($path === '/' ? '/' : $path),
            'image' => $request->getSchemeAndHttpHost().$config['image'],
            'image_alt' => $config['image_alt'],
            'site_name' => $config['site_name'],
            'robots' => $page ? 'index, follow' : 'noindex, nofollow',
        ];
    }
}
