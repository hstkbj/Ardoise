<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends TenantModel
{
    protected function casts(): array
    {
        return ['score' => 'float', 'is_absent' => 'boolean'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
