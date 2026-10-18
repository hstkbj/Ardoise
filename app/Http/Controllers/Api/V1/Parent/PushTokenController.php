<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Téléphones de l'application parents qui reçoivent les notifications push. */
class PushTokenController extends Controller
{
    /** POST /parent/push-token { token, platform?, device_name? } — à chaque ouverture de session. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:120', 'regex:'.PushToken::PATTERN],
            'platform' => ['nullable', 'in:ios,android'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], ['token.regex' => 'Jeton de notification invalide.']);

        // Un téléphone ne sert qu'un compte : s'il change de parent, il suit le dernier connecté
        PushToken::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'last_used_at' => now(),
        ]);

        return response()->json(['message' => 'Notifications activées sur ce téléphone.']);
    }

    /** DELETE /parent/push-token { token } — à la déconnexion. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:120']]);
        $request->user()->pushTokens()->where('token', $data['token'])->delete();

        return response()->json(['message' => 'Notifications désactivées sur ce téléphone.']);
    }
}
