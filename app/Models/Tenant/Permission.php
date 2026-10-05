<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends TenantModel
{
    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
