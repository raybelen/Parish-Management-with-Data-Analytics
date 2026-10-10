<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /** @param list<string> $columns
     * @param  iterable<array<array-key, mixed>>  $rows
     */
    public static function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $columns, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(function ($value): string {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[\s]*[=+@\-\t\r]/u', $value) ? "'".$value : $value;
                }, array_values($row)), ',', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
