<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSnapshot extends CentralModel
{
    protected function casts(): array
    {
        return ['date' => 'date', 'campuses' => 'array', 'admins' => 'array', 'last_activity' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
