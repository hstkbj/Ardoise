<?php

namespace App\Models\Tenant;

use App\Models\Concerns\RestrictedToUserCampuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends TenantModel
{
    use SoftDeletes, RestrictedToUserCampuses;

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'unpaid_waiver' => 'boolean'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->latest('enrolled_on');
    }

    /** Inscription active (classe courante). */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->where('status', 'active')->latestOfMany('enrolled_on');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentProfile::class, 'parent_student')->withPivot('relation', 'is_primary');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function feeAssignments(): HasMany
    {
        return $this->hasMany(FeeAssignment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** Format affiché : « TRAORÉ Awa » */
    public function getFullNameAttribute(): string
    {
        return trim(mb_strtoupper($this->last_name).' '.$this->first_name);
    }

    public function currentClass(): ?ClassRoom
    {
        return $this->currentEnrollment?->classRoom;
    }

    /** @param  array<int, int>  $campusIds */
    public function constrainToCampuses(Builder $query, array $campusIds): void
    {
        $query->where(fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereHas('classRoom'))->orWhereDoesntHave('enrollments'));
    }
}
