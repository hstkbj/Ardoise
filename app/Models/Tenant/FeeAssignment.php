<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Échéance due par un élève. Le statut « overdue » est calculé à la lecture. */
class FeeAssignment extends TenantModel
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_amount' => 'integer', 'due_date' => 'date'];
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function lastPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'paid')->latestOfMany();
    }

    public function remaining(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    public function displayStatus(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday()) {
            return 'overdue';
        }

        return $this->status;
    }

    public function scopeWithDisplayStatus(Builder $query, string $status): Builder
    {
        $today = now()->toDateString();

        return match ($status) {
            'paid' => $query->where('status', 'paid'),
            'overdue' => $query->where('status', '!=', 'paid')->whereDate('due_date', '<', $today),
            'partial', 'pending' => $query->where('status', $status)->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', $today)),
            default => $query,
        };
    }
}
