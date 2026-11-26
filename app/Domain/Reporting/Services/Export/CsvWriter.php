<?php

namespace App\Domain\Reporting\Services\Export;

final class CsvWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array>  $rows  list-shaped rows
     */
    public function write(array $headers, iterable $rows): string
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
        fputcsv($h, array_map([$this, 'cell'], $headers), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($h, array_map([$this, 'cell'], array_values($row)), ',', '"', '');
        }
        rewind($h);
        $csv = stream_get_contents($h);
        fclose($h);

        return $csv;
    }

    /** Neutralises spreadsheet formula injection (CSV injection). */
    public function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value));
        }
        $value = (string) $value;
        if ($value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
