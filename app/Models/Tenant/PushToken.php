<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jeton push Expo d'un téléphone (un parent peut en avoir plusieurs, un téléphone n'appartient qu'à un compte). */
class PushToken extends TenantModel
{
    /** Format des jetons remis par expo-notifications : ExponentPushToken[xxxx] */
    public const PATTERN = '/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,100}\]$/';

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
