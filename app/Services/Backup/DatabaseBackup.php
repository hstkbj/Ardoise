<?php

namespace App\Services\Backup;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;

/**
 * Sauvegarde des bases : centrale + une par école.
 *
 *  - SQLite : copie cohérente (VACUUM INTO), même pendant l'utilisation ;
 *  - MySQL / MariaDB : mysqldump --single-transaction (sans verrouiller les tables) ;
 *  - PostgreSQL : pg_dump.
 *
 * Chaque fichier est compressé (gzip) puis déposé sur le disque configuré
 * (BACKUP_DISK : local, s3…) dans {BACKUP_PATH}/{AAAA-MM-JJ_HHMMSS}/.
 */
class DatabaseBackup
{
    public function __construct(protected TenantManager $tenancy) {}

    /** Dossier de la sauvegarde en cours : backups/2026-10-14_013000 */
    public function folder(?Carbon $at = null): string
    {
        return trim((string) config('ardoise.backup.path'), '/').'/'.($at ?? now())->format('Y-m-d_His');
    }

    /** @return string|null chemin sur le disque, ou null si la base centrale est en mémoire (tests) */
    public function backupCentral(string $folder): ?string
    {
        $config = config('database.connections.central');

        if ($config['driver'] === 'sqlite' && in_array($config['database'], [':memory:', ''], true)) {
            return null;
        }

        return $this->store($config, $folder, 'central');
    }

    public function backupTenant(Tenant $tenant, string $folder): string
    {
        $config = $this->tenancy->connectionTemplate();
        $config['database'] = $this->tenancy->databasePath($tenant);

        return $this->store($config, $folder, 'tenant_'.$tenant->code);
    }

    /** Supprime les dossiers de sauvegarde plus anciens que BACKUP_KEEP_DAYS. */
    public function prune(): int
    {
        $keepDays = max(1, (int) config('ardoise.backup.keep_days'));
        $limit = now()->subDays($keepDays)->format('Y-m-d');
        $deleted = 0;

        foreach ($this->disk()->directories(trim((string) config('ardoise.backup.path'), '/')) as $directory) {
            $date = substr(basename($directory), 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date < $limit) {
                $this->disk()->deleteDirectory($directory);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @param  array<string, mixed>  $config */
    protected function store(array $config, string $folder, string $name): string
    {
        $dump = tempnam(sys_get_temp_dir(), 'ardoise-backup-');
        $gzip = $dump.'.gz';

        try {
            $extension = match ($config['driver']) {
                'sqlite' => $this->dumpSqlite($config, $dump),
                'mysql', 'mariadb' => $this->dumpMysql($config, $dump),
                'pgsql' => $this->dumpPgsql($config, $dump),
                default => throw new RuntimeException("Pilote {$config['driver']} non pris en charge pour les sauvegardes."),
            };

            $this->compress($dump, $gzip);
            $path = $folder.'/'.$name.'.'.$extension.'.gz';
            $stream = fopen($gzip, 'rb');

            try {
                if (! $this->disk()->writeStream($path, $stream)) {
                    throw new RuntimeException("Écriture impossible sur le disque de sauvegarde ({$path}).");
                }
            } finally {
                is_resource($stream) && fclose($stream);
            }

            return $path;
        } finally {
            File::delete([$dump, $gzip]);
        }
    }

    protected function dumpSqlite(array $config, string $target): string
    {
        if (! File::exists($config['database'])) {
            throw new RuntimeException('Fichier introuvable : '.$config['database']);
        }

        File::delete($target);
        $pdo = new PDO('sqlite:'.$config['database']);
        $pdo->exec('VACUUM INTO '.$pdo->quote($target));

        return 'sqlite';
    }

    protected function dumpMysql(array $config, string $target): string
    {
        $command = [
            config('ardoise.backup.mysqldump'),
            '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            '--result-file='.$target,
            $config['database'],
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        }

        // Mot de passe par variable d'environnement : jamais visible dans la liste des processus
        $this->run($command, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);

        return 'sql';
    }

    protected function dumpPgsql(array $config, string $target): string
    {
        $this->run([
            config('ardoise.backup.pg_dump'),
            '--no-owner', '--no-privileges', '--format=plain',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 5432),
            '--username='.($config['username'] ?? 'postgres'),
            '--file='.$target,
            $config['database'],
        ], ['PGPASSWORD' => (string) ($config['password'] ?? '')]);

        return 'sql';
    }

    /** @param  array<int, string>  $command */
    protected function run(array $command, array $env): void
    {
        $result = Process::timeout((int) config('ardoise.backup.timeout', 1800))->env($env)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'La commande '.basename($command[0]).' a échoué (code '.$result->exitCode().').');
        }
    }

    protected function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        if (! $in || ! $out) {
            throw new RuntimeException('Compression impossible.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('ardoise.backup.disk'));
    }
}
