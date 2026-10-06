<?php

namespace App\Http\Resources;

use App\Services\Billing\SubscriptionBilling;
use App\Services\Payments\SchoolFedaPay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Utilisateur connecté (GET /auth/me). Le frontend en déduit son espace et ses menus. */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = tenant();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->primaryRole(),
            'roles' => $this->roleKeys(),
            'permissions' => $this->permissionKeys(),
            'teacher_id' => $this->teacher?->id,
            'parent_id' => $this->parentProfile?->id,
            'last_login_at' => $this->last_login_at,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'domain' => $tenant->primaryDomain(),
            ] : null,
            // Modules du plan : l'interface masque ceux qui ne sont pas inclus
            'features' => $tenant?->features() ?? [],
            // État de l'abonnement (personnel uniquement) : bandeaux, blocage, page Abonnement
            'subscription' => $tenant && $this->isStaff() ? app(SubscriptionBilling::class)->summary($tenant) : null,
            'online_payment' => $tenant && $this->hasRole('parent') ? app(SchoolFedaPay::class)->isEnabled() : false,
        ];
    }
}
