<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): MeResource
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('tenant.users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Les parents mettent à jour leurs coordonnées de contact, pas d'identifiants de connexion
        if ($profile = $user->parentProfile) {
            $profile->update(['phone' => $data['phone'] ?? $profile->phone, 'email' => $data['email'] ?? $profile->email]);
            $user->update(['name' => $data['name']]);
        } else {
            $user->update(['name' => $data['name'], 'email' => isset($data['email']) ? strtolower($data['email']) : $user->email, 'phone' => Phone::normalize($data['phone'] ?? null) ?? $user->phone]);
        }

        return new MeResource($user->load('roles'));
    }

    public function password(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('parent') && ! $user->password, 422, 'Les parents se connectent avec leur code d’accès.');

        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current'], (string) $user->password)) {
            throw ValidationException::withMessages(['current' => 'Mot de passe actuel incorrect.']);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
