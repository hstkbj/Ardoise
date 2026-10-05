<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une matière enseignée dans une classe, avec son enseignant et son coefficient. */
class ClassSubject extends TenantModel
{
    protected $table = 'class_subject';

    protected function casts(): array
    {
        return ['coefficient' => 'float'];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
