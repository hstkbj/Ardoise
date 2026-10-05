<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends TenantModel
{
    protected function casts(): array
    {
        return ['default_coefficient' => 'float'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }
}
