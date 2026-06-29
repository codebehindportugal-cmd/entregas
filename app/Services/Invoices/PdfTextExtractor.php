<?php

namespace App\Services\Invoices;

use Spatie\PdfToText\Pdf;

class PdfTextExtractor
{
    public function extract(string $pdfPath): string
    {
        try {
            $binary = config('invoices.pdftotext_binary', 'pdftotext');
            return Pdf::getText($pdfPath, $binary);
        } catch (\Throwable) {
            return '';
        }
    }
}
