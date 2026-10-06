<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiement d'abonnement d'une école à la plateforme.
 *
 * provider : manual (saisi par la plateforme) | fedapay
 * status   : pending | paid | failed
 */
class SubscriptionPayment extends CentralModel
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'integer', 'months' => 'integer', 'meta' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
