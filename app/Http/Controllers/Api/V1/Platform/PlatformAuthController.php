<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Connexion des superadmins : garde « platform », domaine central uniquement. */
class PlatformAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::guard('platform')->attempt(['email' => strtolower($data['login']), 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('tenant_id');

        $admin = Auth::guard('platform')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => $this->present($admin)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user('platform'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté.']);
    }

    protected function present($admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'superadmin',
            'roles' => ['superadmin'],
            'permissions' => ['*'],
            'tenant' => null,
        ];
    }
}
