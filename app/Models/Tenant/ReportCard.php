<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends TenantModel
{
    protected function casts(): array
    {
        return [
            'general_average' => 'float',
            'class_average' => 'float',
            'absences_hours' => 'float',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ReportCardSubject::class);
    }
}
