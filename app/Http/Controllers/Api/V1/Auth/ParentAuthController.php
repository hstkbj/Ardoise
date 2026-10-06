<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ParentProfile;
use App\Services\ParentAccessService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Connexion des parents par code d'accès uniquement.
 *
 *   POST /api/v1/parent/login { code: "K7QM-4XPA-9R2T", device_name?: "iPhone de Mariam" }
 *
 * Le serveur retrouve l'école et le parent à partir du code (empreinte en base
 * centrale), connecte la base de l'école et ouvre la session (SPA) ou renvoie
 * un jeton « t_{école}.{jeton} » pour l'application mobile.
 */
class ParentAuthController extends Controller
{
    public function __construct(protected TenantManager $tenancy, protected ParentAccessService $codes) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $entry = $this->codes->find($data['code']);
        $invalid = ValidationException::withMessages(['code' => 'Code invalide. Vérifiez-le ou contactez l’établissement.']);

        // Sur un sous-domaine d'école, le code doit appartenir à cette école
        if (! $entry || (tenant() && tenant()->id !== $entry->tenant_id)) {
            throw $invalid;
        }

        $tenant = $entry->tenant;

        if (! $tenant->isAccessible()) {
            abort(423, 'L’accès à cet établissement est suspendu. Contactez l’établissement.');
        }

        if (! $tenant->hasFeature('parent_portal')) {
            abort(403, 'L’espace parent n’est pas inclus dans l’abonnement de cet établissement.');
        }

        $this->tenancy->connect($tenant);

        $parent = ParentProfile::with('user')->find($entry->parent_profile_id);

        if (! $parent || $parent->status === 'inactive' || ! $parent->user || $parent->user->status === 'suspended') {
            throw $invalid;
        }

        $entry->forceFill(['last_used_at' => now()])->save();
        $parent->status === 'pending' && $parent->update(['status' => 'active']);
        $parent->user->forceFill(['last_login_at' => now()])->save();

        return AuthController::startSession($request, $parent->user, $tenant, true, 'parent.login');
    }
}
