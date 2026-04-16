<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PdfDocumentService
{
    public function inline(
        string $view,
        array $data,
        string $filename,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): Response {
        $safeFilename = $this->normalizeFilename($filename);

        return Pdf::setOption([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])
            ->loadView($view, [...$data, 'pdfMode' => true])
            ->setPaper($paper, $orientation)
            ->stream($safeFilename);
    }

    private function normalizeFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'pdf');
        $slug = Str::slug($name, '-');

        if ($slug === '') {
            $slug = 'document';
        }

        return $slug.'.'.$extension;
    }
}
