<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['hired_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class);
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Identifiants des classes où l'enseignant intervient (cours ou professeur principal). */
    public function classRoomIds(): array
    {
        return ClassRoom::query()
            ->where(fn (Builder $q) => $q
                ->where('head_teacher_id', $this->id)
                ->orWhereHas('classSubjects', fn ($s) => $s->where('teacher_id', $this->id)))
            ->pluck('id')
            ->all();
    }
}
