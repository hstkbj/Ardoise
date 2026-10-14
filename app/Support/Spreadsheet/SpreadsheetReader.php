<?php

namespace App\Support\Spreadsheet;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lecture d'un fichier CSV (séparateur ; , ou tabulation, UTF-8 ou Windows-1252)
 * ou Excel .xlsx (première feuille), sans dépendance externe.
 *
 * Renvoie les lignes indexées par en-tête normalisé : « Date de naissance » → « date_de_naissance ».
 * Chaque ligne porte son numéro dans le fichier (clé « _line ») pour les messages d'erreur.
 */
class SpreadsheetReader
{
    public const MAX_ROWS = 5000;

    /** @return array<int, array<string, string>> */
    public static function read(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $matrix = match ($extension) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            default => throw ValidationException::withMessages(['file' => 'Format non pris en charge : envoyez un fichier .xlsx ou .csv.']),
        };

        return self::withHeaders($matrix);
    }

    /** « Prénom(s) » → « prenoms », « N° de téléphone » → « n_de_telephone ». */
    public static function normalizeHeader(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(trim($header)))), '_');
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @return array<int, array<string, string>>
     */
    protected static function withHeaders(array $matrix): array
    {
        // Première ligne non vide = en-têtes
        while ($matrix && ! array_filter(reset($matrix), fn ($v) => trim((string) $v) !== '')) {
            array_shift($matrix);
        }

        if (! $matrix) {
            throw ValidationException::withMessages(['file' => 'Le fichier est vide.']);
        }

        $headerLine = array_key_first($matrix);
        $headers = array_map(fn ($h) => self::normalizeHeader((string) $h), $matrix[$headerLine]);
        unset($matrix[$headerLine]);

        $rows = [];

        foreach ($matrix as $line => $cells) {
            if (! array_filter($cells, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }

            $row = ['_line' => (string) $line];

            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($cells[$i] ?? ''));
                }
            }

            $rows[] = $row;

            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'Fichier trop long : '.self::MAX_ROWS.' lignes au maximum par import.']);
            }
        }

        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'Aucune ligne de données sous les en-têtes.']);
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> lignes numérotées à partir de 1 */
    protected static function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $matrix = [];
        $line = 0;

        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $matrix[++$line] = array_map(fn ($v) => (string) $v, $cells);
        }

        fclose($stream);

        return $matrix;
    }

    /** @return array<int, array<int, string>> lignes numérotées comme dans Excel */
    protected static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['file' => 'Les fichiers Excel ne peuvent pas être lus sur ce serveur (extension zip manquante) : enregistrez-le au format CSV.']);
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Fichier Excel illisible.']);
        }

        try {
            $strings = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));

            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'Feuille Excel introuvable.']);
            }

            $sheet = self::xml($sheetXml);
            $matrix = [];

            foreach ($sheet->sheetData->row ?? [] as $row) {
                $line = (int) $row['r'];
                $cells = [];

                foreach ($row->c as $cell) {
                    $index = self::columnIndex((string) $cell['r']);
                    $type = (string) $cell['t'];

                    $cells[$index] = match ($type) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => self::richText($cell->is),
                        'b' => ((string) $cell->v) === '1' ? 'oui' : 'non',
                        default => self::number((string) $cell->v),
                    };
                }

                if ($cells) {
                    $max = max(array_keys($cells));
                    $matrix[$line] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
                }
            }

            return $matrix;
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    protected static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach (self::xml($xml)->si as $si) {
            $strings[] = self::richText($si);
        }

        return $strings;
    }

    protected static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $book = self::xml($workbook);
        $sheet = $book->sheets->sheet[0] ?? null;
        $relId = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;

        foreach (self::xml($rels)->Relationship as $relation) {
            if ((string) $relation['Id'] === $relId) {
                $target = ltrim((string) $relation['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    protected static function richText(?SimpleXMLElement $node): string
    {
        if (! $node) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';

        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** « 15.5 » reste « 15.5 » ; « 3.0000000000000004 » → « 3 ». */
    protected static function number(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $float = round((float) $value, 10);

        return floor($float) == $float ? (string) (int) $float : rtrim(rtrim(number_format($float, 10, '.', ''), '0'), '.');
    }

    protected static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d/', '', strtoupper($reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    protected static function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw ValidationException::withMessages(['file' => 'Fichier Excel illisible.']);
        }

        return $xml;
    }
}
