<?php

use Illuminate\Support\Facades\Route;

/*
| SPA Vue : toutes les URLs (hors /api, /sanctum, /up) renvoient la vue unique
| « app ». Vue Router gère ensuite la navigation (resources/js/router).
*/
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|up|storage).*$')
    ->name('spa');
