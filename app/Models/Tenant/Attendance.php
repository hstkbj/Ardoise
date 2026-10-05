<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends TenantModel
{
    protected function casts(): array
    {
        return ['date' => 'date', 'is_justified' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }
}
