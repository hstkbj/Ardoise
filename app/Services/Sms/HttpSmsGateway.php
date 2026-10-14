<?php

namespace App\Services\Sms;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Passerelle HTTP générique, configurable sans code pour la plupart des agrégateurs SMS :
 *
 *   SMS_HTTP_URL=https://api.fournisseur.com/sms/send
 *   SMS_HTTP_METHOD=post            post | get
 *   SMS_HTTP_FORMAT=json            json | form | query
 *   SMS_HTTP_AUTH=bearer            bearer | basic | header | query | none
 *   SMS_HTTP_TOKEN=…                jeton (bearer, header, query) ou « utilisateur:mot_de_passe » (basic)
 *   SMS_HTTP_AUTH_KEY=X-API-Key     nom de l'en-tête (header) ou du paramètre (query)
 *   SMS_HTTP_FIELDS=to:to,from:from,message:message   noms des champs attendus par le fournisseur
 *   SMS_HTTP_EXTRA={"type":"text"}  champs fixes ajoutés à chaque envoi (JSON)
 *   SMS_HTTP_SUCCESS_PATH=status    (facultatif) champ de la réponse à vérifier…
 *   SMS_HTTP_SUCCESS_VALUE=success  …et sa valeur attendue
 */
class HttpSmsGateway extends AbstractHttpSmsGateway
{
    protected function requiredConfig(): array
    {
        return ['url'];
    }

    protected function deliver(string $to, string $message): bool
    {
        $fields = $this->fieldNames();
        $payload = array_merge($this->extra(), [
            $fields['to'] => $to,
            $fields['from'] => $this->sender,
            $fields['message'] => $message,
        ]);

        $request = Http::timeout((int) ($this->config['timeout'] ?? 10))->acceptJson();
        $token = (string) ($this->config['token'] ?? '');
        $authKey = (string) ($this->config['auth_key'] ?? 'X-API-Key');

        $request = match ($this->config['auth'] ?? 'bearer') {
            'basic' => $request->withBasicAuth(...array_pad(explode(':', $token, 2), 2, '')),
            'header' => $request->withHeaders([$authKey => $token]),
            'query' => tap($request, function () use (&$payload, $authKey, $token) {
                $payload[$authKey] = $token;
            }),
            'none' => $request,
            default => $request->withToken($token),
        };

        $method = strtolower((string) ($this->config['method'] ?? 'post'));
        $format = $this->config['format'] ?? 'json';

        $response = match (true) {
            $method === 'get' || $format === 'query' => $request->{$method === 'get' ? 'get' : 'post'}($this->config['url'].(str_contains($this->config['url'], '?') ? '&' : '?').http_build_query($payload)),
            $format === 'form' => $request->asForm()->post($this->config['url'], $payload),
            default => $request->asJson()->post($this->config['url'], $payload),
        };

        if (! $response->successful()) {
            return false;
        }

        $path = $this->config['success_path'] ?? null;

        return blank($path) || (string) Arr::get($response->json() ?? [], $path) === (string) ($this->config['success_value'] ?? '');
    }

    /** @return array{to: string, from: string, message: string} */
    protected function fieldNames(): array
    {
        $names = ['to' => 'to', 'from' => 'from', 'message' => 'message'];

        foreach (array_filter(explode(',', (string) ($this->config['fields'] ?? ''))) as $pair) {
            [$key, $name] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, null);

            if (isset($names[$key]) && filled($name)) {
                $names[$key] = $name;
            }
        }

        return $names;
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        $extra = $this->config['extra'] ?? null;

        return is_array($extra) ? $extra : (json_decode((string) $extra, true) ?: []);
    }
}
