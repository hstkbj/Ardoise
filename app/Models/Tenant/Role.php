<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends TenantModel
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
