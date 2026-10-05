<?php

namespace App\Tenancy;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Détermine l'école courante, côté serveur uniquement :
 *
 *  1. sous-domaine ou domaine personnalisé : palmiers.ardoise.app → « palmiers » ;
 *  2. sur le domaine central, jeton mobile préfixé : « t_palmiers.12|xxxx »
 *     (le jeton est ensuite vérifié DANS la base de cette école : un préfixe
 *     falsifié ne donne accès à rien) ;
 *  3. sur le domaine central, session serveur ouverte après une connexion
 *     (code parent ou code établissement).
 *
 * Aucun en-tête ni paramètre envoyé par le client ne désigne directement l'école.
 */
class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());

        if (! $this->isCentralHost($host)) {
            return $this->fromHost($host);
        }

        if ($code = $this->tokenTenantCode($request)) {
            return Tenant::where('code', $code)->first();
        }

        if ($request->hasSession() && ($id = $request->session()->get('tenant_id'))) {
            return Tenant::find($id);
        }

        return null;
    }

    public function isCentralHost(string $host): bool
    {
        return in_array(strtolower($host), config('tenancy.central_domains'), true);
    }

    public function fromHost(string $host): ?Tenant
    {
        $id = Cache::remember("tenancy:host:{$host}", 300, function () use ($host) {
            if ($domain = Domain::where('domain', $host)->first()) {
                return $domain->tenant_id;
            }

            $suffix = '.'.config('tenancy.base_domain');

            return str_ends_with($host, $suffix)
                ? Tenant::where('code', Str::beforeLast($host, $suffix))->value('id')
                : null;
        });

        return $id ? Tenant::find($id) : null;
    }

    /** Code école contenu dans un jeton mobile « t_{code}.{jeton} ». */
    public function tokenTenantCode(Request $request): ?string
    {
        $token = $request->bearerToken();
        $prefix = config('ardoise.token_prefix');

        if (! $token || ! str_starts_with($token, $prefix) || ! str_contains($token, '.')) {
            return null;
        }

        $code = Str::between($token, $prefix, '.');

        return preg_match('/^[a-z0-9-]{2,40}$/', $code) ? $code : null;
    }

    /** Retire le préfixe école du jeton pour que Sanctum le vérifie normalement. */
    public function stripTokenPrefix(Request $request): void
    {
        $token = $request->bearerToken();
        $prefix = config('ardoise.token_prefix');

        if ($token && str_starts_with($token, $prefix) && str_contains($token, '.')) {
            $request->headers->set('Authorization', 'Bearer '.Str::after($token, '.'));
        }
    }

    /** Jeton complet à remettre à l'application mobile. */
    public static function prefixToken(Tenant $tenant, string $plainToken): string
    {
        return config('ardoise.token_prefix').$tenant->code.'.'.$plainToken;
    }
}
