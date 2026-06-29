<x-layouts.app :title="'Rever Fatura '.($invoice->invoice_number ?? '#'.$invoice->id)">
    <x-page-title :title="'Rever — '.($invoice->invoice_number ?? '#'.$invoice->id)"
                  :subtitle="$invoice->supplier_name ?? 'Sem fornecedor'">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $invoice->statusBadgeClass() }}">
                {{ $invoice->statusLabel() }}
            </span>
            <a href="{{ route('invoices.show', $invoice) }}"
               class="rounded-lg border border-white/10 bg-[#151E2D] px-4 py-2 text-sm text-slate-300 hover:bg-white/10">
                ← Detalhes
            </a>
        </div>
    </x-page-title>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            {{-- Campos da fatura (guardar rascunho) --}}
            <form method="post" action="{{ route('invoices.review.update', $invoice) }}" id="review-form">
                @csrf
                @method('PUT')

                <div class="rounded border border-white/10 bg-[#151E2D] p-5 space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Dados da fatura</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs text-slate-400">Fornecedor</span>
                            <input name="supplier_name"
                                   value="{{ old('supplier_name', $invoice->supplier_name) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white placeholder-slate-600 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">NIF / NIPC</span>
                            <input name="supplier_tax_number"
                                   value="{{ old('supplier_tax_number', $invoice->supplier_tax_number) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white placeholder-slate-600 focus:border-emerald-500 focus:outline-none"
                                   placeholder="000000000">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">Nº Fatura</span>
                            <input name="invoice_number"
                                   value="{{ old('invoice_number', $invoice->invoice_number) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white placeholder-slate-600 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">Moeda</span>
                            <select name="currency"
                                    class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                @foreach(['EUR','USD','GBP','BRL'] as $c)
                                    <option value="{{ $c }}" {{ old('currency', $invoice->currency) === $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">Data da Fatura</span>
                            <input type="date" name="invoice_date"
                                   value="{{ old('invoice_date', $invoice->invoice_date?->format('Y-m-d')) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">Vencimento</span>
                            <input type="date" name="due_date"
                                   value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">Subtotal</span>
                            <input type="number" step="0.01" name="subtotal"
                                   value="{{ old('subtotal', $invoice->subtotal) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs text-slate-400">IVA total</span>
                            <input type="number" step="0.01" name="tax_total"
                                   value="{{ old('tax_total', $invoice->tax_total) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="text-xs text-slate-400">Total <span class="text-red-400">*</span></span>
                            <input type="number" step="0.01" name="total"
                                   value="{{ old('total', $invoice->total) }}"
                                   class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm font-semibold text-white focus:border-emerald-500 focus:outline-none">
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                                class="rounded-lg bg-slate-600 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-500">
                            Guardar rascunho
                        </button>
                    </div>
                </div>
            </form>

            {{-- Linhas da fatura + Confirmar --}}
            <form method="post" action="{{ route('invoices.confirm', $invoice) }}" id="confirm-form">
                @csrf

                {{-- Mirror fields from review-form are synced by JS before submit --}}
                <input type="hidden" name="supplier_name"       id="cf_supplier_name">
                <input type="hidden" name="supplier_tax_number" id="cf_supplier_tax_number">
                <input type="hidden" name="invoice_number"      id="cf_invoice_number">
                <input type="hidden" name="currency"            id="cf_currency">
                <input type="hidden" name="invoice_date"        id="cf_invoice_date">
                <input type="hidden" name="due_date"            id="cf_due_date">
                <input type="hidden" name="subtotal"            id="cf_subtotal">
                <input type="hidden" name="tax_total"           id="cf_tax_total">
                <input type="hidden" name="total"               id="cf_total">

                <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Linhas da fatura</p>
                        <button type="button" onclick="addItemRow()"
                                class="rounded bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/30">
                            + Adicionar linha
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="items-table">
                            <thead class="text-xs text-slate-500">
                                <tr>
                                    <th class="pb-2 pl-1 text-left">Descrição</th>
                                    <th class="pb-2 text-right" style="width:80px">Qtd</th>
                                    <th class="pb-2 text-right" style="width:110px">Preço unit.</th>
                                    <th class="pb-2 text-right" style="width:75px">IVA %</th>
                                    <th class="pb-2 text-right" style="width:110px">Total</th>
                                    <th class="pb-2" style="width:30px"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                @forelse($invoice->items as $idx => $item)
                                    <tr class="item-row border-t border-white/5">
                                        <td class="py-1.5 pr-2">
                                            <input name="items[{{ $idx }}][description]"
                                                   value="{{ $item->description }}"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-sm text-white focus:border-emerald-500 focus:outline-none"
                                                   placeholder="Descrição">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.001"
                                                   name="items[{{ $idx }}][quantity]"
                                                   value="{{ $item->quantity }}"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.0001"
                                                   name="items[{{ $idx }}][unit_price]"
                                                   value="{{ $item->unit_price }}"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.01"
                                                   name="items[{{ $idx }}][tax_rate]"
                                                   value="{{ $item->tax_rate }}"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.01"
                                                   name="items[{{ $idx }}][total]"
                                                   value="{{ $item->total }}"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 text-center">
                                            <button type="button" onclick="removeRow(this)"
                                                    class="text-xs text-red-400 hover:text-red-300">✕</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="item-row border-t border-white/5">
                                        <td class="py-1.5 pr-2">
                                            <input name="items[0][description]" value=""
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-sm text-white focus:border-emerald-500 focus:outline-none"
                                                   placeholder="Descrição">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.001" name="items[0][quantity]" value="1"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.0001" name="items[0][unit_price]" value=""
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.01" name="items[0][tax_rate]" value="0"
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input type="number" step="0.01" name="items[0][total]" value=""
                                                   class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        </td>
                                        <td class="py-1.5 text-center">
                                            <button type="button" onclick="removeRow(this)"
                                                    class="text-xs text-red-400 hover:text-red-300">✕</button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-white/10 pt-4">
                        <p class="mr-auto self-center text-xs text-slate-500">
                            Preencha todos os campos obrigatórios antes de confirmar.
                        </p>
                        <button type="submit"
                                onclick="return syncAndConfirm()"
                                class="rounded-lg bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600">
                            Confirmar e gravar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Raw text sidebar --}}
        @if($invoice->raw_extracted_text)
            <div>
                <details class="rounded border border-white/10 bg-[#151E2D]" open>
                    <summary class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-300">
                        Texto OCR extraído
                    </summary>
                    <div class="border-t border-white/10 p-3">
                        <pre class="max-h-[65vh] overflow-auto whitespace-pre-wrap font-mono text-xs leading-relaxed text-slate-500">{{ $invoice->raw_extracted_text }}</pre>
                    </div>
                </details>
            </div>
        @endif
    </div>

    <script>
        let rowCount = {{ max($invoice->items->count(), 1) }};

        function addItemRow() {
            const tbody = document.getElementById('items-tbody');
            const idx   = rowCount++;
            const tr    = document.createElement('tr');
            tr.className = 'item-row border-t border-white/5';
            tr.innerHTML = `
                <td class="py-1.5 pr-2">
                    <input name="items[${idx}][description]" value=""
                           class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-sm text-white focus:border-emerald-500 focus:outline-none"
                           placeholder="Descrição">
                </td>
                <td class="py-1.5 pr-2">
                    <input type="number" step="0.001" name="items[${idx}][quantity]" value="1"
                           class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                </td>
                <td class="py-1.5 pr-2">
                    <input type="number" step="0.0001" name="items[${idx}][unit_price]" value=""
                           class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                </td>
                <td class="py-1.5 pr-2">
                    <input type="number" step="0.01" name="items[${idx}][tax_rate]" value="0"
                           class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                </td>
                <td class="py-1.5 pr-2">
                    <input type="number" step="0.01" name="items[${idx}][total]" value=""
                           class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-right text-sm text-white focus:border-emerald-500 focus:outline-none">
                </td>
                <td class="py-1.5 text-center">
                    <button type="button" onclick="removeRow(this)" class="text-xs text-red-400 hover:text-red-300">✕</button>
                </td>
            `;
            tbody.appendChild(tr);
            tr.querySelector('input').focus();
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
        }

        function syncAndConfirm() {
            const rf = document.getElementById('review-form');
            ['supplier_name','supplier_tax_number','invoice_number','currency',
             'invoice_date','due_date','subtotal','tax_total','total'].forEach(name => {
                const src = rf.querySelector(`[name="${name}"]`);
                const dst = document.getElementById(`cf_${name}`);
                if (src && dst) dst.value = src.value;
            });
            return true;
        }
    </script>
</x-layouts.app>
