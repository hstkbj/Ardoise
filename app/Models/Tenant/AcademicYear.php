<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends TenantModel
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('position');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Année active, sinon la plus récente. */
    public static function current(): ?self
    {
        return static::where('status', 'active')->first() ?? static::latest('starts_on')->first();
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Les classes ne dépendent pas de l'année : les écritures (inscriptions, présences,
     * évaluations) se font dans l'année courante, qui ne doit pas être clôturée.
     */
    public static function assertOpen(string $field = 'class_id'): self
    {
        $year = static::current();

        if (! $year || $year->isClosed()) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Aucune année scolaire ouverte : activez ou créez une année avant de continuer.']);
        }

        return $year;
    }
}
