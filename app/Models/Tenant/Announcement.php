<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends TenantModel
{
    protected function casts(): array
    {
        return ['audiences' => 'array', 'channels' => 'array', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }
}
