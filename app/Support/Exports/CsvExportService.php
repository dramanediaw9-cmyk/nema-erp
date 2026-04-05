<?php

namespace App\Support\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
