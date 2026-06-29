<?php

namespace App\Services\Invoices;

class PdfOcrExtractor
{
    public function extract(string $pdfPath): string
    {
        $ocrmypdf  = config('invoices.ocrmypdf_binary', 'ocrmypdf');
        $pdftotext = config('invoices.pdftotext_binary', 'pdftotext');
        $lang      = config('invoices.tesseract_language', 'por+eng');

        $ocrOutput  = tempnam(sys_get_temp_dir(), 'ocr_pdf_') . '.pdf';
        $textOutput = tempnam(sys_get_temp_dir(), 'ocr_txt_');

        try {
            // Produce a searchable PDF with OCRmyPDF
            $cmd = sprintf(
                '%s --skip-text -l %s %s %s 2>&1',
                escapeshellcmd($ocrmypdf),
                escapeshellarg($lang),
                escapeshellarg($pdfPath),
                escapeshellarg($ocrOutput)
            );
            exec($cmd, $out, $rc);

            if ($rc !== 0 || ! file_exists($ocrOutput)) {
                return '';
            }

            // Extract text from the searchable PDF
            $extractCmd = sprintf(
                '%s -layout %s %s 2>&1',
                escapeshellcmd($pdftotext),
                escapeshellarg($ocrOutput),
                escapeshellarg($textOutput)
            );
            exec($extractCmd, $_, $rc2);

            if ($rc2 !== 0 || ! file_exists($textOutput)) {
                return '';
            }

            return file_get_contents($textOutput) ?: '';
        } finally {
            if (file_exists($ocrOutput)) {
                @unlink($ocrOutput);
            }
            if (file_exists($textOutput)) {
                @unlink($textOutput);
            }
        }
    }
}
