<?php

namespace App\Domain\Reporting\Services\Export;

use RuntimeException;
use ZipArchive;

/**
 * Minimal dependency-free Office Open XML (.xlsx) writer. Supports one sheet
 * with a bold, frozen header row, numbers stored as numbers and auto-filter.
 * Uses inline strings, so no shared-string table is required.
 */
final class XlsxWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array>  $rows
     */
    public function write(array $headers, iterable $rows, string $sheetName = 'Report'): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for Excel exports.');
        }

        $sheetName = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $sheetName) ?: 'Report', 0, 31);
        $rowsXml = $this->row(1, $headers, true);
        $r = 1;
        foreach ($rows as $row) {
            $rowsXml .= $this->row(++$r, array_values($row), false);
        }
        $lastCol = $this->column(max(1, count($headers)));
        $widths = '';
        foreach ($headers as $i => $h) {
            $widths .= sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, max(10, min(60, mb_strlen((string) $h) + 4)));
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($widths ? '<cols>'.$widths.'</cols>' : '')
            .'<sheetData>'.$rowsXml.'</sheetData>'
            .'<autoFilter ref="A1:'.$lastCol.max(1, $r).'"/>'
            .'</worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel file.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->xml($sheetName).'</dc:title><dc:creator>StageTrack Enterprise</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.$this->xml(str_replace("'", "''", $sheetName)).'\'!$A$1:$'.$lastCol.'$'.max(1, $r).'</definedName></definedNames></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4F4F5"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0"/><xf fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function row(int $r, array $values, bool $header): string
    {
        $cells = '';
        foreach ($values as $i => $v) {
            $ref = $this->column($i + 1).$r;
            $style = $header ? ' s="1"' : '';
            if (! $header && (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && ! preg_match('/^0\d/', $v) && strlen($v) < 16))) {
                $cells .= '<c r="'.$ref.'"'.$style.'><v>'.($v + 0).'</v></c>';
            } elseif (is_bool($v)) {
                $cells .= '<c r="'.$ref.'"'.$style.' t="b"><v>'.($v ? 1 : 0).'</v></c>';
            } elseif ($v === null || $v === '') {
                $cells .= '<c r="'.$ref.'"'.$style.'/>';
            } else {
                $text = is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)) : (string) $v;
                $cells .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.$this->xml($text).'</t></is></c>';
            }
        }

        return '<row r="'.$r.'">'.$cells.'</row>';
    }

    public function column(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    private function xml(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
