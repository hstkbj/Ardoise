<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'full_name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->primaryRole(),
            'roles' => $this->roleKeys(),
            'school_ids' => $this->whenLoaded('campuses', fn () => $this->campuses->pluck('id')),
            'school_name' => $this->whenLoaded('campuses', fn () => $this->campuses->pluck('name')->implode(', ') ?: 'Tous'),
            'status' => $this->status,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
