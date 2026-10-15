<?php

namespace App\Models\Tenant;

use App\Models\Concerns\RestrictedToUserCampuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends TenantModel
{
    use RestrictedToUserCampuses;

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

    /** @param  array<int, int>  $campusIds */
    public function constrainToCampuses(Builder $query, array $campusIds): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('announcements.campus_id')->orWhereIn('announcements.campus_id', $campusIds));
    }
}
