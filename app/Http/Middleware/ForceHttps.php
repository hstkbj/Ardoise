<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FORCE_HTTPS=true : redirige tout accès HTTP vers HTTPS et envoie l'en-tête HSTS.
 *
 * Derrière un reverse proxy, TRUSTED_PROXIES doit être renseigné : sinon Laravel ne voit
 * jamais la requête comme sécurisée et la redirection boucle. /up reste joignable en HTTP
 * (sondes de santé du proxy ou de Docker).
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ardoise.force_https')) {
            return $next($request);
        }

        if (! $request->secure()) {
            if ($request->is('up')) {
                return $next($request);
            }

            // 301 pour la navigation, 308 pour conserver la méthode et le corps des autres requêtes
            return redirect()->secure($request->getRequestUri(), $request->isMethodSafe() ? 301 : 308);
        }

        $response = $next($request);
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
