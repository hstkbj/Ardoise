<?php

namespace App\Support\Spreadsheet;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Classeur Excel (.xlsx) à plusieurs feuilles, sans dépendance externe : textes uniquement,
 * en-têtes en gras sur la première ligne de chaque feuille. Relu par SpreadsheetReader::readSheets().
 */
class XlsxWriter
{
    /**
     * @param  array<string, array<int, array<int, mixed>>>  $sheets  nom de la feuille => lignes (la première = en-têtes)
     */
    public static function download(string $filename, array $sheets): StreamedResponse
    {
        $content = self::build($sheets);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @param  array<string, array<int, array<int, mixed>>>  $sheets */
    public static function build(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer le fichier Excel.');
        }

        $names = array_keys($sheets);
        $overrides = '';
        $sheetTags = '';
        $relations = '';

        foreach ($names as $i => $name) {
            $n = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetTags .= '<sheet name="'.self::escape(self::sheetName($name)).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relations .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::sheetXml($sheets[$name]));
        }

        $relations .= '<Relationship Id="rId'.(count($names) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xmlHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

        $zip->addFromString('[Content_Types].xml', $xmlHeader.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', $xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $xmlHeader.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetTags.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $xmlHeader.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'</Relationships>');
        $zip->addFromString('xl/styles.xml', $xmlHeader.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDCEBE7"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();

        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /** @param  array<int, array<int, mixed>>  $rows */
    protected static function sheetXml(array $rows): string
    {
        $width = max(1, ...array_map('count', $rows ?: [[]]));
        $cols = '<cols><col min="1" max="'.$width.'" width="24" customWidth="1"/></cols>';
        $data = '';

        foreach (array_values($rows) as $r => $cells) {
            $data .= '<row r="'.($r + 1).'">';

            foreach (array_values($cells) as $c => $value) {
                $value = (string) ($value ?? '');

                if ($value === '') {
                    continue;
                }

                $style = $r === 0 ? ' s="1"' : '';
                $data .= '<c r="'.self::columnName($c).($r + 1).'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::escape($value).'</t></is></c>';
            }

            $data .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$cols.'<sheetData>'.$data.'</sheetData></worksheet>';
    }

    protected static function columnName(int $index): string
    {
        $name = '';

        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    /** Excel : 31 caractères au plus, sans []:*?/\ . */
    protected static function sheetName(string $name): string
    {
        return mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name), 0, 31);
    }

    protected static function escape(string $value): string
    {
        // Caractères de contrôle interdits en XML 1.0
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
