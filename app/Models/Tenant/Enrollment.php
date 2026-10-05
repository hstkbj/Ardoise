<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends TenantModel
{
    protected function casts(): array
    {
        return ['enrolled_on' => 'date', 'left_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
