<?php

namespace App\Console\Commands;

use App\Models\Central\ParentAccessCode;
use App\Models\Central\Tenant;
use App\Models\Tenant\ParentProfile;
use App\Services\ParentAccessService;
use App\Support\AccessCode;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Throwable;

/**
 * Remet dans la base centrale (parent_access_codes) les codes parents qui n'y sont pas :
 * écoles créées avant la table, base d'école restaurée, base centrale reconstruite…
 *
 * Pour chaque parent actif, le code conservé (chiffré) dans la base de l'école est
 * réenregistré tel quel : le parent garde le code qu'il a déjà. Les parents sans code,
 * ou dont le code est illisible (APP_KEY changée), reçoivent un nouveau code avec --generate.
 */
class SyncParentCodes extends Command
{
    protected $signature = 'parents:sync-codes
        {--tenant=* : Code(s) d\'école (toutes par défaut)}
        {--generate : Créer un code pour les parents qui n\'en ont pas (ou dont le code est illisible)}
        {--dry-run : Afficher ce qui serait fait sans rien enregistrer}';

    protected $description = 'Synchronise les codes d\'accès des parents avec la base centrale';

    /** @var array<string, int> */
    protected array $counts = [];

    public function handle(TenantManager $tenancy, ParentAccessService $codes): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $list) => $q->whereIn('code', $list))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Aucune école trouvée.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->counts = ['synced' => 0, 'present' => 0, 'generated' => 0, 'missing' => 0, 'inactive' => 0, 'conflict' => 0];

            try {
                $tenancy->run($tenant, fn () => $this->syncTenant($tenant, $codes, $dryRun));
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$tenant->code} : {$e->getMessage()}");

                continue;
            }

            $rows[] = [$tenant->code, $this->counts['synced'], $this->counts['present'], $this->counts['generated'], $this->counts['missing'], $this->counts['inactive'], $this->counts['conflict']];
        }

        $this->table(['École', 'Ajoutés', 'Déjà présents', 'Nouveaux codes', 'Sans code', 'Accès désactivé', 'Conflits'], $rows);

        if ($dryRun) {
            $this->info('Simulation : rien n\'a été enregistré.');
        }

        if (collect($rows)->sum(fn ($r) => $r[4]) > 0 && ! $this->option('generate')) {
            $this->line('Des parents n\'ont pas de code utilisable : relancez avec --generate pour leur en créer un (à leur remettre ensuite).');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function syncTenant(Tenant $tenant, ParentAccessService $codes, bool $dryRun): void
    {
        ParentProfile::orderBy('id')->each(function (ParentProfile $parent) use ($tenant, $codes, $dryRun) {
            // Accès désactivé volontairement par l'école : on n'y touche pas
            if ($parent->status === 'inactive') {
                $this->counts['inactive']++;

                return;
            }

            $code = $this->readCode($parent);

            if ($code === null) {
                if (! $this->option('generate')) {
                    $this->counts['missing']++;

                    return;
                }

                if (! $dryRun) {
                    $codes->issue($parent);
                }

                $this->counts['generated']++;

                return;
            }

            $hash = AccessCode::hash($code, (string) config('app.key'));
            $existing = ParentAccessCode::where('code_hash', $hash)->first();

            if ($existing && ($existing->tenant_id !== $tenant->id || (int) $existing->parent_profile_id !== $parent->id)) {
                // Même code déjà attribué ailleurs (cas extrêmement rare) : il faut un nouveau code
                $this->counts['conflict']++;
                $this->warn("{$tenant->code} : le code du parent n° {$parent->id} ({$parent->full_name}) est déjà utilisé ailleurs. Générez-lui un nouveau code depuis sa fiche.");

                return;
            }

            if ($existing) {
                $this->counts['present']++;

                return;
            }

            if (! $dryRun) {
                // Un seul code actif par parent : l'éventuelle ancienne empreinte est désactivée
                ParentAccessCode::where('tenant_id', $tenant->id)->where('parent_profile_id', $parent->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                ParentAccessCode::create([
                    'tenant_id' => $tenant->id,
                    'parent_profile_id' => $parent->id,
                    'code_hash' => $hash,
                    'code_last4' => substr($code, -4),
                ]);
            }

            $this->counts['synced']++;
        });
    }

    /** Code en clair conservé dans la base de l'école, ou null (absent / illisible). */
    protected function readCode(ParentProfile $parent): ?string
    {
        try {
            $code = $parent->access_code;
        } catch (DecryptException) {
            return null;
        }

        $code = AccessCode::normalize((string) $code);

        return AccessCode::isWellFormed($code, (int) config('ardoise.parent_codes.length')) ? $code : null;
    }
}
