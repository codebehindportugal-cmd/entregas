<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::latest()->paginate(25);

        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items');

        return view('invoices.show', compact('invoice'));
    }

    public function destroy(Invoice $invoice)
    {
        $disk = config('invoices.storage_disk', 'local');

        // Delete all uploaded files (single or multi-page)
        $paths = array_filter(array_merge(
            $invoice->original_file_paths ?? [],
            array_filter([$invoice->original_file_path, $invoice->processed_file_path])
        ));

        foreach (array_unique($paths) as $path) {
            Storage::disk($disk)->delete($path);
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('status', 'Fatura eliminada.');
    }
}
