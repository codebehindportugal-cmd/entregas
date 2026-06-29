<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmInvoiceRequest;
use App\Http\Requests\UpdateInvoiceReviewRequest;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceReviewController extends Controller
{
    public function edit(Invoice $invoice)
    {
        $invoice->load('items');

        return view('invoices.review', compact('invoice'));
    }

    public function update(UpdateInvoiceReviewRequest $request, Invoice $invoice)
    {
        $invoice->update(array_merge($request->validated(), ['status' => 'reviewed']));

        return redirect()
            ->route('invoices.review', $invoice)
            ->with('status', 'Dados guardados. Reveja as linhas e confirme quando estiver pronto.');
    }

    public function confirm(ConfirmInvoiceRequest $request, Invoice $invoice)
    {
        DB::transaction(function () use ($request, $invoice) {
            $fields = collect($request->validated())->except('items')->all();
            $invoice->update(array_merge($fields, ['status' => 'confirmed']));

            $invoice->items()->delete();

            foreach ($request->validated()['items'] ?? [] as $i => $itemData) {
                $invoice->items()->create(array_merge($itemData, ['line_order' => $i]));
            }
        });

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'Fatura confirmada e gravada com sucesso.');
    }
}
