<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Empreinte d'un code d'accès parent → (école, parent). */
class ParentAccessCode extends CentralModel
{
    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
