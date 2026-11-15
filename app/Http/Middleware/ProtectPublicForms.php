<?php

namespace App\Http\Middleware;

use App\Support\SpamGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-spam des formulaires publics : voir App\Support\SpamGuard.
 * « spam.guard » : champ piège et jeton de temps ; « spam.guard:captcha » ajoute Turnstile (si configuré).
 */
class ProtectPublicForms
{
    public function handle(Request $request, Closure $next, string $level = 'basic'): Response
    {
        if (! SpamGuard::enabled()) {
            return $next($request);
        }

        // Robot : on répond « succès » sans rien enregistrer, pour ne pas lui donner d'indice
        if (SpamGuard::honeypotTripped($request)) {
            return response()->json(['message' => 'Demande envoyée.'], 201);
        }

        $error = SpamGuard::tokenError($request) ?? ($level === 'captcha' ? SpamGuard::captchaError($request) : null);

        if ($error) {
            throw ValidationException::withMessages(['form_token' => $error]);
        }

        return $next($request);
    }
}
