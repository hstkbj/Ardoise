<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Protection anti-spam des formulaires publics (demande de démonstration, lettre d'information).
 *
 *  1. Piège à robots : le champ caché « website » doit rester vide (un robot le remplit).
 *  2. Jeton de formulaire : délivré par le serveur à l'ouverture de la page, chiffré, il prouve
 *     qu'un temps minimum s'est écoulé avant l'envoi (un robot poste instantanément).
 *  3. Cloudflare Turnstile, si TURNSTILE_SITE_KEY et TURNSTILE_SECRET_KEY sont renseignées.
 *
 * Le nombre de requêtes par adresse IP est limité séparément (limiteur « public-forms »).
 */
class SpamGuard
{
    public const HONEYPOT = 'website';

    public const MAX_AGE_SECONDS = 6 * 3600;

    public static function enabled(): bool
    {
        return (bool) config('ardoise.spam.enabled');
    }

    public static function issueToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    public static function turnstileSiteKey(): ?string
    {
        return filled(config('ardoise.spam.turnstile.secret_key')) ? config('ardoise.spam.turnstile.site_key') : null;
    }

    public static function honeypotTripped(Request $request): bool
    {
        return filled($request->input(self::HONEYPOT));
    }

    /** @return string|null Message d'erreur à afficher, ou null si le jeton est valable. */
    public static function tokenError(Request $request): ?string
    {
        try {
            $issuedAt = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (Throwable) {
            return 'Le formulaire a expiré, rechargez la page puis réessayez.';
        }

        $age = time() - $issuedAt;

        if ($age > self::MAX_AGE_SECONDS || $age < 0) {
            return 'Le formulaire a expiré, rechargez la page puis réessayez.';
        }

        if ($age < (int) config('ardoise.spam.min_seconds')) {
            return 'Veuillez patienter un instant avant d’envoyer le formulaire.';
        }

        return null;
    }

    /** @return string|null Message d'erreur, ou null si le défi Turnstile est réussi (ou désactivé). */
    public static function captchaError(Request $request): ?string
    {
        $secret = config('ardoise.spam.turnstile.secret_key');

        if (blank($secret)) {
            return null;
        }

        $answer = (string) $request->input('cf-turnstile-response');

        if ($answer === '') {
            return 'Veuillez confirmer que vous n’êtes pas un robot.';
        }

        try {
            $result = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $answer,
                'remoteip' => $request->ip(),
            ]);
        } catch (Throwable) {
            return 'La vérification anti-robot est momentanément indisponible, réessayez dans un instant.';
        }

        return $result->json('success') === true ? null : 'La vérification anti-robot a échoué, réessayez.';
    }
}
