<?php

namespace App\Http\Resources;

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
        ];
    }
}
