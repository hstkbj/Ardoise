<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends CentralModel
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest();
    }
}
