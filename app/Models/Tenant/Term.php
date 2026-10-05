<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends TenantModel
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Période en cours de l'année active (selon les dates, sinon la première). */
    public static function current(): ?self
    {
        $year = AcademicYear::current();

        if (! $year) {
            return null;
        }

        $today = now()->toDateString();

        return $year->terms()->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->first()
            ?? $year->terms()->first();
    }
}
