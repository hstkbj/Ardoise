<?php

namespace App\Models\Tenant;

use App\Models\Concerns\RestrictedToUserCampuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends TenantModel
{
    use RestrictedToUserCampuses;

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class)->withTrashed();
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function headTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'head_teacher_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Élèves actuellement inscrits. */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->wherePivot('status', 'active')
            ->withPivot('id', 'enrolled_on', 'status')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /** @param  array<int, int>  $campusIds */
    public function constrainToCampuses(Builder $query, array $campusIds): void
    {
        $query->whereIn('class_rooms.campus_id', $campusIds);
    }
}
