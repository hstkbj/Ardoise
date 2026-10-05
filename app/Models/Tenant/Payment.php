<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'date'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FeeAssignment::class, 'fee_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
