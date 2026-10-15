<?php

use Illuminate\Support\Facades\Route;

/*
| Documentation de l'API parents (application mobile) : Swagger UI + spécification OpenAPI.
*/
if (config('ardoise.api_docs')) {
    Route::view('/api/docs', 'swagger')->name('api.docs');
    Route::get('/api/docs/parent.yaml', fn () => response()->file(resource_path('openapi/parent.yaml'), ['Content-Type' => 'application/yaml; charset=utf-8']))->name('api.docs.parent');
}

/*
| SPA Vue : toutes les URLs (hors /api, /sanctum, /up) renvoient la vue unique
| « app ». Vue Router gère ensuite la navigation (resources/js/router).
*/
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|up|storage).*$')
    ->name('spa');
