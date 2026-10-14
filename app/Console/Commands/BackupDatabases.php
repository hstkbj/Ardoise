<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Backup\DatabaseBackup;
use App\Services\PlatformAlerts;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sauvegarde de la base centrale et des bases des écoles, puis purge des
 * sauvegardes anciennes. Un échec n'arrête pas les autres écoles ; les
 * superadmins sont alertés.
 */
class BackupDatabases extends Command
{
    protected $signature = 'backup:run
        {--tenant=* : Code(s) d\'école à sauvegarder (toutes par défaut)}
        {--skip-central : Ne pas sauvegarder la base centrale}
        {--no-prune : Conserver les anciennes sauvegardes}';

    protected $description = 'Sauvegarde la base centrale et chaque base d\'école (fichiers .gz)';

    public function handle(DatabaseBackup $backup, PlatformAlerts $alerts): int
    {
        $folder = $backup->folder();
        $failures = [];
        $done = 0;

        if (! $this->option('skip-central') && ! $this->option('tenant')) {
            try {
                $path = $backup->backupCentral($folder);
                $path ? $this->line("✓ base centrale → {$path}") : $this->warn('Base centrale en mémoire : ignorée.');
                $done += $path ? 1 : 0;
            } catch (Throwable $e) {
                $failures['central'] = $e->getMessage();
                $this->error('✗ base centrale : '.$e->getMessage());
            }
        }

        Tenant::query()
            ->when($this->option('tenant'), fn ($q, $codes) => $q->whereIn('code', $codes))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($backup, $folder, &$failures, &$done) {
                try {
                    $path = $backup->backupTenant($tenant, $folder);
                    $this->line("✓ {$tenant->code} → {$path}");
                    $done++;
                } catch (Throwable $e) {
                    $failures[$tenant->code] = $e->getMessage();
                    $this->error("✗ {$tenant->code} : ".$e->getMessage());
                }
            });

        $pruned = $this->option('no-prune') ? 0 : $backup->prune();
        $this->info("{$done} base(s) sauvegardée(s) dans {$folder}, ".count($failures)." échec(s), {$pruned} ancienne(s) sauvegarde(s) supprimée(s).");

        if ($failures) {
            $alerts->send(
                'backup.failed',
                'Sauvegarde incomplète',
                count($failures).' base(s) n’ont pas pu être sauvegardées : '.collect($failures)->map(fn ($error, $name) => "{$name} ({$error})")->implode(' ; '),
                null,
                'error',
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
