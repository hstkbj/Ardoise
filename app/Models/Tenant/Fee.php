<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fee extends TenantModel
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'installments' => 'integer', 'first_due_date' => 'date'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FeeAssignment::class);
    }
}
