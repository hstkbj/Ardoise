<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Établissement / site d'une organisation scolaire (« schools » côté interface). */
class Campus extends TenantModel
{
    use SoftDeletes;

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }
}
