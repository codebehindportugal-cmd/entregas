<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceUploadRequest;
use App\Jobs\ProcessInvoiceUpload;
use App\Models\Invoice;

class InvoiceUploadController extends Controller
{
    public function create()
    {
        return view('invoices.upload');
    }

    public function store(StoreInvoiceUploadRequest $request)
    {
        $files = $request->file('invoice_files');
        $disk  = config('invoices.storage_disk', 'local');
        $dir   = config('invoices.storage_path', 'private/invoices');

        // Store all files and collect paths
        $storedPaths = [];
        foreach ($files as $file) {
            $storedPaths[] = $file->store($dir, $disk);
        }

        $invoice = Invoice::create([
            'user_id'             => auth()->id(),
            'original_file_path'  => $storedPaths[0],
            // Only persist multi-path array when there are multiple files
            'original_file_paths' => count($storedPaths) > 1 ? $storedPaths : null,
            'mime_type'           => $files[0]->getMimeType(),
            'status'              => 'uploaded',
        ]);

        ProcessInvoiceUpload::dispatch($invoice);

        $pageCount = count($storedPaths);
        $msg = $pageCount > 1
            ? "Fatura enviada ({$pageCount} páginas). A extração está em processamento..."
            : 'Fatura enviada. A extração está em processamento...';

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', $msg);
    }
}
