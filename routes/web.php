<?php

use App\Support\ClientConfig;
use App\Support\SeoMeta;
use App\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Documentation de l'API parents (application mobile) : Swagger UI + spécification OpenAPI.
*/
if (config('ardoise.api_docs')) {
    Route::view('/api/docs', 'swagger')->name('api.docs');
    Route::get('/api/docs/parent.yaml', fn () => response()->file(resource_path('openapi/parent.yaml'), ['Content-Type' => 'application/yaml; charset=utf-8']))->name('api.docs.parent');
}

/*
| Référencement : robots.txt et plan du site des pages publiques (domaine central).
| Les adresses des écoles ne sont jamais indexées : robots.txt y interdit tout, sans plan du site.
*/
Route::get('/robots.txt', function (Request $request, TenantResolver $resolver) {
    $lines = $resolver->isCentralHost($request->getHost())
        ? [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /teacher',
            'Disallow: /superadmin',
            'Disallow: /api',
            'Disallow: /login',
            'Allow: /',
            '',
            'Sitemap: '.$request->getSchemeAndHttpHost().'/sitemap.xml',
        ]
        : ['User-agent: *', 'Disallow: /'];

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->name('robots');

Route::get('/sitemap.xml', function (Request $request, TenantResolver $resolver) {
    abort_unless($resolver->isCentralHost($request->getHost()), 404);

    $base = $request->getSchemeAndHttpHost();
    $urls = collect(array_keys(config('ardoise.seo.pages')))
        ->map(fn (string $path) => '  <url><loc>'.e($base.($path === '/' ? '/' : $path)).'</loc></url>')
        ->implode("\n");

    return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$urls."\n</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=utf-8']);
})->name('sitemap');

/*
| SPA Vue : toutes les URLs (hors /api, /sanctum, /up) renvoient la vue unique
| « app ». Vue Router gère ensuite la navigation (resources/js/router).
| Une adresse que le routeur ne connaît pas reçoit le statut 404 (les moteurs de recherche
| n'indexent pas de fausses pages) ; la page « Page introuvable » de l'application s'affiche.
*/
Route::get('/{any?}', function (Request $request, TenantResolver $resolver) {
    $central = $resolver->isCentralHost($request->getHost());

    return response()->view('app', [
        'seo' => SeoMeta::forRequest($request, $central),
        'client' => ClientConfig::for($central),
    ], ClientConfig::isKnownPath($request->path()) ? 200 : 404);
})
    ->where('any', '^(?!api|sanctum|up|storage).*$')
    ->name('spa');
