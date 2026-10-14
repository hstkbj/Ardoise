<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    /** Objet concerné (élève, paiement…) ; null s'il a été supprimé. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, ?object $subject = null, array $properties = []): void
    {
        static::create([
            // À la connexion, l'utilisateur n'est pas encore authentifié : c'est l'objet de l'action
            'user_id' => auth()->id() ?? ($subject instanceof User && str_starts_with($action, 'login') ? $subject->getKey() : null),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
