<?php

namespace App\Support\Spreadsheet;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fichier CSV qui s'ouvre directement dans Excel (version française) :
 * UTF-8 avec BOM, séparateur point-virgule.
 */
class CsvResponse
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'oui' : 'non') : (string) ($v ?? ''), $row), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
