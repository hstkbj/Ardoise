<?php

namespace App\Services;

use App\Models\Central\ParentAccessCode;
use App\Models\Tenant\ParentProfile;
use App\Support\AccessCode;
use RuntimeException;

/**
 * Codes d'accès des parents.
 *
 * - Le code en clair est conservé chiffré dans la base de l'école (réimpression de la fiche).
 * - La base centrale ne garde qu'une empreinte HMAC → retrouve l'école ET le parent
 *   à partir du seul code saisi dans l'application, sans exposer le code.
 */
class ParentAccessService
{
    public function issue(ParentProfile $parent): string
    {
        $tenant = tenant() ?? throw new RuntimeException('Aucune école connectée.');

        $this->revoke($parent);

        do {
            $code = AccessCode::generate(config('ardoise.parent_codes.length'), config('ardoise.parent_codes.alphabet'));
            $hash = AccessCode::hash($code, $this->key());
        } while (ParentAccessCode::where('code_hash', $hash)->exists());

        ParentAccessCode::create([
            'tenant_id' => $tenant->id,
            'parent_profile_id' => $parent->id,
            'code_hash' => $hash,
            'code_last4' => substr($code, -4),
        ]);

        $parent->forceFill(['access_code' => $code, 'access_code_generated_at' => now()])->save();

        return AccessCode::format($code, config('ardoise.parent_codes.group'));
    }

    /** Invalide le code actuel et déconnecte les appareils du parent. */
    public function revoke(ParentProfile $parent): void
    {
        if ($tenant = tenant()) {
            ParentAccessCode::where('tenant_id', $tenant->id)
                ->where('parent_profile_id', $parent->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        $parent->user?->tokens()->delete();
    }

    public function find(string $input): ?ParentAccessCode
    {
        if (! AccessCode::isWellFormed($input, config('ardoise.parent_codes.length'))) {
            return null;
        }

        return ParentAccessCode::with('tenant')
            ->where('code_hash', AccessCode::hash($input, $this->key()))
            ->whereNull('revoked_at')
            ->first();
    }

    public function formatted(ParentProfile $parent): ?string
    {
        return $parent->access_code ? AccessCode::format($parent->access_code, config('ardoise.parent_codes.group')) : null;
    }

    protected function key(): string
    {
        return (string) config('app.key');
    }
}
