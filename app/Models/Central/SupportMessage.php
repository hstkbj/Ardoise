<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends CentralModel
{
    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
