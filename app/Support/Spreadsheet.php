<?php

namespace App\Support;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Phase 11: reads and writes the timetable files, Excel (.xlsx) and CSV.
 *
 * No extra packages: an .xlsx file is a zip of XML files, so this uses PHP's zip extension
 * (ZipArchive) and its XML reader. Without the zip extension, CSV files still work.
 *
 * read():  every sheet as ['name' => ..., 'rows' => [row number => [column (0 = A) => value]]].
 *          Values are text, or numbers for number cells. Excel keeps a time as part of a day:
 *          0.3125 = 7:30 AM.
 * xlsx():  makes an .xlsx file from sheets. csv(): CSV text.
 */
final class Spreadsheet
{
    /** A timetable never has more rows than this */
    public const MAX_ROWS = 3000;

    /** Columns A to AN are read; the rest are ignored */
    public const MAX_COLUMNS = 40;

    /** A small upload that unpacks into something huge isn't a timetable */
    private const MAX_UNPACKED_BYTES = 60 * 1024 * 1024;

    public const NO_ZIP = 'This computer’s PHP can’t open Excel files yet (its zip extension is off). Upload a CSV file instead (Excel: File → Save As → CSV UTF-8), or turn the extension on: see “Timetable” in the README.';

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const STRICT_REL_NS = 'http://purl.oclc.org/ooxml/officeDocument/relationships';

    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Cell styles (their order in STYLES below) */
    private const STYLE = ['none' => 0, 'header' => 1, 'time' => 2, 'wrap' => 3, 'title' => 4, 'text' => 5];

    private const STYLES = self::XML.'<styleSheet xmlns="'.self::MAIN_NS.'">'
        .'<numFmts count="1"><numFmt numFmtId="164" formatCode="h:mm AM/PM"/></numFmts>'
        .'<fonts count="3">'
        .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        .'<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>'
        .'</fonts>'
        .'<fills count="3">'
        .'<fill><patternFill patternType="none"/></fill>'
        .'<fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF6D28D9"/><bgColor indexed="64"/></patternFill></fill>'
        .'</fills>'
        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="6">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';

    /** True when .xlsx files can be read and made here (PHP's zip extension is on) */
    public static function canXlsx(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Every sheet of an .xlsx or .csv file.
     *
     * @return list<array{name: string, rows: array<int, array<int, string|float>>}>
     *
     * @throws SpreadsheetProblem
     */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx' => self::quietly(fn () => self::readXlsx($path)),
            'csv', 'txt' => [['name' => 'CSV', 'rows' => self::readCsv((string) file_get_contents($path))]],
            default => throw new SpreadsheetProblem('Upload an Excel file (.xlsx) or a CSV file (.csv).'),
        };
    }

    /**
     * The rows of CSV text: [row number => [column => text]]. Commas, semicolons or tabs;
     * UTF-8 (with or without a BOM), UTF-16, or the older Windows encoding Excel uses for plain CSV.
     *
     * @return array<int, array<int, string>>
     */
    public static function readCsv(string $text): array
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        } elseif (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
            $text = mb_convert_encoding(substr($text, 2), 'UTF-8', str_starts_with($text, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);
        $delimiter = self::delimiter($text);
        $rows = [];
        $number = 0;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false && $number < self::MAX_ROWS * 2) {
            $number++;
            foreach ($cells as $column => $value) {
                $value = (string) $value;
                // A cell saved as '=… by csv() below (so Excel shows it as text) reads back without the '
                if (preg_match("/^'[=+@]/", $value)) {
                    $value = substr($value, 1);
                }
                if ($column < self::MAX_COLUMNS && trim($value) !== '') {
                    $rows[$number][$column] = $value;
                }
            }
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Makes an .xlsx file and returns its path: a temporary file to delete after sending it.
     *
     * Each sheet: name, rows (lists of cells: text, a number, null, or ['time' => minutes after
     * midnight]), and optionally:
     *   widths  [column => width in characters]
     *   styles  [column => 'time', 'text' or 'wrap'], for the whole column, so rows typed in Excel get it too
     *   header  true: row 1 has the column names (bold on purple, stays on screen, filter buttons)
     *   title   true: row 1 is a title (big and bold)
     *   lists   [column => '"Mon,Tue,Wed"' or 'Lists!$A$2:$A$9']: a dropdown (other values are still allowed)
     */
    public static function xlsx(array $sheets): string
    {
        if (! self::canXlsx()) {
            throw new SpreadsheetProblem(self::NO_ZIP);
        }
        $path = tempnam(sys_get_temp_dir(), 'timetable');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not make the Excel file.');
        }

        $types = '';
        $rels = '';
        $list = '';
        $names = '';
        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::sheetXml($sheet, $i === 0));
            $types .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="'.self::REL_NS.'/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $list .= '<sheet name="'.self::esc($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            if (! empty($sheet['header'])) {
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">'
                    .self::esc(self::sheetRef($sheet['name']).'!$A$1:$'.self::columnName(self::width($sheet) - 1).'$'.max(1, count($sheet['rows'])))
                    .'</definedName>';
            }
        }

        $zip->addFromString('[Content_Types].xml', self::XML.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$types.'</Types>');
        $zip->addFromString('_rels/.rels', self::XML.'<Relationships xmlns="'.self::PKG_REL_NS.'">'
            .'<Relationship Id="rId1" Type="'.self::REL_NS.'/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::XML.'<Relationships xmlns="'.self::PKG_REL_NS.'">'.$rels
            .'<Relationship Id="rId'.(count($sheets) + 1).'" Type="'.self::REL_NS.'/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', self::XML.'<workbook xmlns="'.self::MAIN_NS.'" xmlns:r="'.self::REL_NS.'">'
            .'<bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$list.'</sheets>'
            .($names !== '' ? '<definedNames>'.$names.'</definedNames>' : '').'</workbook>');
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->close();

        return $path;
    }

    /**
     * CSV text, UTF-8 with a BOM so Excel shows letters like ñ right. A time (['time' => minutes])
     * is written like 7:30 AM.
     */
    public static function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $cells) {
            $cells = array_map(function ($value) {
                $text = is_array($value) ? TimeSlots::clock($value['time']) : (string) ($value ?? '');

                // Excel would run text starting with = + @ as a formula: a ' in front keeps it text
                return preg_match('/^[=+@]/', $text) ? "'".$text : $text;
            }, $cells);
            fputcsv($stream, $cells, ',', '"', '', "\r\n");
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    /** 'B12' -> 1, 'AA3' -> 26 (column numbers start at 0 = A) */
    public static function columnIndex(string $ref): int
    {
        if (! preg_match('/^([A-Za-z]{1,3})/', $ref, $m)) {
            return -1;
        }
        $index = 0;
        foreach (str_split(strtoupper($m[1])) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }

    /** 0 -> 'A', 26 -> 'AA' */
    public static function columnName(int $index): string
    {
        $name = '';
        for ($n = max(0, $index) + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    // ---- Reading .xlsx ----

    private static function readXlsx(string $path): array
    {
        if (! self::canXlsx()) {
            throw new SpreadsheetProblem(self::NO_ZIP);
        }
        $broken = 'This file couldn’t be opened as an Excel workbook. Open it in Excel and save it again as “Excel Workbook (.xlsx)”. If it has a password, remove the password first.';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new SpreadsheetProblem($broken);
        }

        try {
            $unpacked = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $unpacked += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if ($zip->numFiles > 2000 || $unpacked > self::MAX_UNPACKED_BYTES) {
                throw new SpreadsheetProblem('This file is too big to be a timetable. Keep only the timetable sheet and try again.');
            }

            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook === false) {
                throw new SpreadsheetProblem($broken);
            }
            $parts = self::relationships((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
            $strings = self::sharedStrings($zip, $parts);

            $sheets = [];
            foreach (self::sheetList($workbook) as [$name, $relId]) {
                $xml = isset($parts[$relId]) ? $zip->getFromName($parts[$relId]['path']) : false;
                if ($xml !== false) {
                    $sheets[] = ['name' => $name, 'rows' => self::sheetRows($xml, $strings)];
                }
            }
            if (! $sheets) {
                throw new SpreadsheetProblem($broken);
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /** Runs $read with XML warnings turned off: a damaged file gives a message, not an error page */
    private static function quietly(callable $read): mixed
    {
        $before = libxml_use_internal_errors(true);
        try {
            return $read();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
    }

    private static function reader(string $xml): ?XMLReader
    {
        if ($xml === '') {
            return null;
        }
        $reader = new XMLReader;

        return $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT) ? $reader : null;
    }

    /** A rels file: [id => ['type' => ..., 'path' => 'xl/worksheets/sheet1.xml']] */
    private static function relationships(string $xml): array
    {
        $rels = [];
        $reader = self::reader($xml);
        while ($reader && @$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                $rels[(string) $reader->getAttribute('Id')] = [
                    'type' => (string) $reader->getAttribute('Type'),
                    'path' => self::partPath((string) $reader->getAttribute('Target')),
                ];
            }
        }

        return $rels;
    }

    /** 'worksheets/sheet1.xml' or '/xl/worksheets/sheet1.xml' -> 'xl/worksheets/sheet1.xml' */
    private static function partPath(string $target): string
    {
        $path = str_starts_with($target, '/') ? $target : 'xl/'.$target;
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    /** The workbook's sheets in order: [[name, relationship id], ...] */
    private static function sheetList(string $xml): array
    {
        $sheets = [];
        $reader = self::reader($xml);
        while ($reader && @$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $id = $reader->getAttributeNs('id', self::REL_NS) ?? $reader->getAttributeNs('id', self::STRICT_REL_NS);
                if ($id !== null) {
                    $sheets[] = [(string) $reader->getAttribute('name'), $id];
                }
            }
        }

        return $sheets;
    }

    /** Text that cells point to by number (most text in an .xlsx file is kept here) */
    private static function sharedStrings(ZipArchive $zip, array $parts): array
    {
        $path = 'xl/sharedStrings.xml';
        foreach ($parts as $part) {
            if (str_ends_with($part['type'], '/sharedStrings')) {
                $path = $part['path'];
                break;
            }
        }

        $strings = [];
        $text = null;
        $phonetic = 0;      // inside <rPh>: reading hints for Asian text, not part of the text
        $reader = self::reader((string) $zip->getFromName($path));
        while ($reader && @$reader->read() && count($strings) < 500000) {
            $name = $reader->localName;
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($name === 'si' && $reader->isEmptyElement) {
                    $strings[] = '';
                } elseif ($name === 'si') {
                    $text = '';
                } elseif ($name === 'rPh' && ! $reader->isEmptyElement) {
                    $phonetic++;
                } elseif ($name === 't' && $text !== null && $phonetic === 0) {
                    $text .= $reader->readString();
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($name === 'si' && $text !== null) {
                    $strings[] = $text;
                    $text = null;
                } elseif ($name === 'rPh') {
                    $phonetic = max(0, $phonetic - 1);
                }
            }
        }

        return $strings;
    }

    /** One sheet's filled cells: [row number => [column => value]] */
    private static function sheetRows(string $xml, array $strings): array
    {
        $rows = [];
        $row = 0;
        $column = -1;
        $cell = null;       // while inside a <c>: its type and value
        $phonetic = 0;
        $reader = self::reader($xml);
        while ($reader && @$reader->read()) {
            $name = $reader->localName;
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($name === 'row') {
                    $r = (string) $reader->getAttribute('r');
                    $row = ctype_digit($r) ? (int) $r : $row + 1;
                    $column = -1;
                    if ($row > self::MAX_ROWS * 2) {
                        break;      // far past any timetable
                    }
                } elseif ($name === 'c') {
                    $ref = (string) $reader->getAttribute('r');
                    $column = $ref !== '' ? self::columnIndex($ref) : $column + 1;
                    $cell = $reader->isEmptyElement ? null : ['type' => $reader->getAttribute('t') ?? 'n', 'value' => null];
                } elseif ($name === 'rPh' && ! $reader->isEmptyElement) {
                    $phonetic++;
                } elseif ($cell !== null && $phonetic === 0 && ($name === 'v' || ($name === 't' && $cell['type'] === 'inlineStr'))) {
                    $cell['value'] = ($cell['value'] ?? '').$reader->readString();
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($name === 'c') {
                    if ($cell !== null && $cell['value'] !== null && $column >= 0 && $column < self::MAX_COLUMNS) {
                        $value = self::cellValue($cell['type'], $cell['value'], $strings);
                        if ($value !== '') {
                            $rows[$row][$column] = $value;
                        }
                    }
                    $cell = null;
                } elseif ($name === 'rPh') {
                    $phonetic = max(0, $phonetic - 1);
                }
            }
        }

        return $rows;
    }

    private static function cellValue(string $type, string $raw, array $strings): string|float
    {
        return match ($type) {
            's' => $strings[(int) $raw] ?? '',
            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
            'e' => '',                                          // an error such as #N/A
            'n' => is_numeric($raw) ? (float) $raw : $raw,
            default => $raw,                                    // text: 'str' (a formula's result), 'inlineStr', 'd'
        };
    }

    /** The separator a CSV file uses (from its first line): comma, semicolon or tab */
    private static function delimiter(string $text): string
    {
        $line = strtok($text, "\n") ?: '';
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return reset($counts) > 0 ? (string) array_key_first($counts) : ',';
    }

    // ---- Writing .xlsx ----

    private static function sheetXml(array $sheet, bool $first): string
    {
        $rows = array_values($sheet['rows']);
        $styles = $sheet['styles'] ?? [];
        $header = ! empty($sheet['header']);
        $last = self::columnName(self::width($sheet) - 1);

        $xml = self::XML.'<worksheet xmlns="'.self::MAIN_NS.'" xmlns:r="'.self::REL_NS.'">'
            .'<dimension ref="A1:'.$last.max(1, count($rows)).'"/>'
            .'<sheetViews><sheetView workbookViewId="0"'.($first ? ' tabSelected="1"' : '').'>'
            .($header ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/>' : '')
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>';

        if (! empty($sheet['widths'])) {
            $widths = $sheet['widths'];
            ksort($widths);
            $xml .= '<cols>';
            foreach ($widths as $column => $width) {
                $style = isset($styles[$column]) ? ' style="'.self::STYLE[$styles[$column]].'"' : '';
                $xml .= '<col min="'.($column + 1).'" max="'.($column + 1).'" width="'.$width.'" customWidth="1"'.$style.'/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($rows as $r => $cells) {
            $number = $r + 1;
            $xml .= '<row r="'.$number.'">';
            foreach (array_values($cells) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $style = match (true) {
                    $r === 0 && $header => self::STYLE['header'],
                    $r === 0 && ! empty($sheet['title']) => self::STYLE['title'],
                    is_array($value) => self::STYLE['time'],
                    default => self::STYLE[$styles[$c] ?? 'none'],
                };
                $xml .= '<c r="'.self::columnName($c).$number.'"'.($style ? ' s="'.$style.'"' : '');
                $xml .= match (true) {
                    is_array($value) => '><v>'.self::number($value['time'] / 1440).'</v></c>',
                    is_int($value) || is_float($value) => '><v>'.self::number($value).'</v></c>',
                    default => ' t="inlineStr"><is><t'.(trim((string) $value) !== (string) $value ? ' xml:space="preserve"' : '').'>'.self::esc((string) $value).'</t></is></c>',
                };
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($header) {
            $xml .= '<autoFilter ref="A1:'.$last.max(1, count($rows)).'"/>';
        }
        if (! empty($sheet['lists'])) {
            $xml .= '<dataValidations count="'.count($sheet['lists']).'">';
            foreach ($sheet['lists'] as $column => $formula) {
                $letter = self::columnName($column);
                $xml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="0" sqref="'.$letter.'2:'.$letter.self::MAX_ROWS.'">'
                    .'<formula1>'.self::esc($formula).'</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }

        return $xml.'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
    }

    /** How many columns the sheet uses */
    private static function width(array $sheet): int
    {
        $width = 1;
        foreach ($sheet['rows'] as $cells) {
            $width = max($width, count($cells));
        }

        return $width;
    }

    /** A sheet's name as formulas write it: Lists, or 'How to fill this in' */
    private static function sheetRef(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) ? $name : "'".str_replace("'", "''", $name)."'";
    }

    /**
     * 0.3125 -> '0.3125', with every digit PHP keeps: 5:00 PM is 0.7083333333333334, and cutting it
     * shorter makes Excel show 4:59 PM. Never a comma, whatever the computer's language.
     */
    private static function number(int|float $n): string
    {
        return is_int($n) ? (string) $n : var_export($n, true);
    }

    /** Text safe inside XML: & < > " escaped, characters XML can't hold removed */
    private static function esc(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
