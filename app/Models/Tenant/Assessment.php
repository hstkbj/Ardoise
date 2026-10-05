<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends TenantModel
{
    public const TYPES = ['devoir', 'interrogation', 'examen', 'composition', 'controle_continu', 'oral'];

    protected function casts(): array
    {
        return ['date' => 'date', 'coefficient' => 'float', 'max_score' => 'float', 'validated_at' => 'datetime'];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'validated';
    }
}
