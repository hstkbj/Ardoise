<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends CentralModel
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'amount' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
