<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Invoices\InvoiceExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInvoiceUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 180;

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(InvoiceExtractionService $service): void
    {
        $this->invoice->update(['status' => 'processing']);

        try {
            $result = $service->extract($this->invoice);
            $data   = $result['data'];

            $this->invoice->update([
                'raw_extracted_text'  => $result['raw_text'],
                'extracted_data'      => $data,
                'supplier_name'       => $data['supplier_name'],
                'supplier_tax_number' => $data['supplier_tax_number'],
                'invoice_number'      => $data['invoice_number'],
                'invoice_date'        => $data['invoice_date'],
                'due_date'            => $data['due_date'],
                'subtotal'            => $data['subtotal'],
                'tax_total'           => $data['tax_total'],
                'total'               => $data['total'],
                'currency'            => $data['currency'] ?? 'EUR',
                'status'              => 'extracted',
                'error_message'       => null,
            ]);
        } catch (\Throwable $e) {
            $this->invoice->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
