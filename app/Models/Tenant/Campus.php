<?php

namespace App\Models\Tenant;

use App\Models\Concerns\RestrictedToUserCampuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Établissement / site d'une organisation scolaire (« schools » côté interface). */
class Campus extends TenantModel
{
    use SoftDeletes, RestrictedToUserCampuses;

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    /** @param  array<int, int>  $campusIds */
    public function constrainToCampuses(Builder $query, array $campusIds): void
    {
        $query->whereIn('campuses.id', $campusIds);
    }
}
