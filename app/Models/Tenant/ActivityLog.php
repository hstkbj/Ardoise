<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends TenantModel
{
    protected $table = 'activity_log';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?object $subject = null, array $properties = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
