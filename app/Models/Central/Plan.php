<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends CentralModel
{
    protected function casts(): array
    {
        return ['features' => 'array', 'price' => 'integer'];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
