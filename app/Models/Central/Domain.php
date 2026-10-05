<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends CentralModel
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
