<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceExtractionService
{
    public function __construct(
        private readonly PdfTextExtractor  $pdfText,
        private readonly PdfOcrExtractor   $pdfOcr,
        private readonly ImageOcrExtractor $imageOcr,
        private readonly InvoiceParser     $parser,
        private readonly InvoiceItemParser $itemParser,
    ) {}

    public function extract(Invoice $invoice): array
    {
        $disk   = config('invoices.storage_disk', 'local');
        $mime   = (string) $invoice->mime_type;
        $minLen = (int) config('invoices.min_pdf_text_length', 100);

        $rawText = '';

        if (str_contains($mime, 'pdf')) {
            // pdftotext extracts ALL pages by default — no changes needed for multi-page PDFs
            $mainPath = Storage::disk($disk)->path((string) $invoice->original_file_path);
            $rawText  = $this->pdfText->extract($mainPath);

            if (strlen(trim($rawText)) < $minLen) {
                $rawText = $this->pdfOcr->extract($mainPath);
            }
        } else {
            // Images: collect all file paths (multi-page upload stores them in original_file_paths)
            $paths = $this->resolveImagePaths($invoice, $disk);
            $rawText = $this->imageOcr->extractAll($paths);
        }

        $data          = $this->parser->parse($rawText);
        $data['items'] = $this->itemParser->parse($rawText);

        return [
            'raw_text' => $rawText,
            'data'     => $data,
        ];
    }

    /**
     * Returns an ordered list of absolute filesystem paths for all image files in this invoice.
     *
     * @return string[]
     */
    private function resolveImagePaths(Invoice $invoice, string $disk): array
    {
        $stored = $invoice->original_file_paths;

        if (!empty($stored) && is_array($stored)) {
            return array_map(
                fn($p) => Storage::disk($disk)->path($p),
                $stored
            );
        }

        return [Storage::disk($disk)->path((string) $invoice->original_file_path)];
    }

    public function emptyData(): array
    {
        return [
            'supplier_name'       => null,
            'supplier_tax_number' => null,
            'invoice_number'      => null,
            'invoice_date'        => null,
            'due_date'            => null,
            'subtotal'            => null,
            'tax_total'           => null,
            'total'               => null,
            'currency'            => 'EUR',
            'items' => [[
                'description' => '',
                'quantity'    => 1,
                'unit_price'  => 0,
                'tax_rate'    => 0,
                'tax_amount'  => 0,
                'total'       => 0,
            ]],
            'confidence' => array_fill_keys(
                ['supplier_name', 'supplier_tax_number', 'invoice_number', 'invoice_date', 'due_date', 'subtotal', 'tax_total', 'total', 'items'],
                0
            ),
        ];
    }
}
