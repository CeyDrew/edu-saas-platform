<?php

namespace App\Domain\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV that opens correctly in Turkish Excel: UTF-8 BOM and ";" as the separator.
 */
final class CsvExport
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array<int|string, mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'evet' : 'hayır') : $v, array_values($row)), ';', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Kuruş to "1234,50" (decimal comma).
     */
    public static function money(int $kurus): string
    {
        return number_format($kurus / 100, 2, ',', '');
    }
}
