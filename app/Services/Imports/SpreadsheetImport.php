<?php

namespace App\Services\Imports;

use App\Support\Spreadsheet\CellParser;
use Illuminate\Support\Str;

/**
 * Base des imports : toutes les lignes sont vérifiées avant d'enregistrer quoi que
 * ce soit ; au moindre problème, l'import est refusé avec la liste des erreurs.
 */
abstract class SpreadsheetImport
{
    /** @var array<int, array{line: int, message: string}> */
    protected array $errors = [];

    /** En-têtes du modèle à télécharger (ligne 1 du fichier). */
    abstract public static function templateHeaders(): array;

    /** Ligne d'exemple du modèle. */
    abstract public static function templateExample(): array;

    protected function error(array $row, string $message): void
    {
        $this->errors[] = ['line' => (int) ($row['_line'] ?? 0), 'message' => $message];
    }

    protected function failIfErrors(): void
    {
        if ($this->errors) {
            throw new ImportException($this->errors);
        }
    }

    /** @param  array<int, array<string, string>>  $rows */
    protected function requireColumns(array $rows, string ...$columns): void
    {
        $present = array_keys($rows[0] ?? []);
        $missing = array_filter($columns, fn ($c) => ! in_array($c, $present, true));

        if ($missing) {
            throw new ImportException([['line' => 1, 'message' => 'Colonne(s) manquante(s) : '.implode(', ', $missing).'. Utilisez le modèle proposé au téléchargement.']]);
        }
    }

    protected static function key(?string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim((string) $value))));
    }

    protected function cell(array $row, string ...$keys): string
    {
        return CellParser::pick($row, ...$keys);
    }
}
