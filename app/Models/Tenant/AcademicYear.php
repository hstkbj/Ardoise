<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends TenantModel
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('position');
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    /** Année active, sinon la plus récente. */
    public static function current(): ?self
    {
        return static::where('status', 'active')->first() ?? static::latest('starts_on')->first();
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
