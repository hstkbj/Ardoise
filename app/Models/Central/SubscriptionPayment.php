<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends CentralModel
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
