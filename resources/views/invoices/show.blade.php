<x-layouts.app :title="'Fatura '.($invoice->invoice_number ?? '#'.$invoice->id)">
    <x-page-title :title="'Fatura '.($invoice->invoice_number ?? '#'.$invoice->id)"
                  :subtitle="$invoice->supplier_name ?? 'Sem fornecedor'">
        <div class="flex flex-wrap gap-2">
            @if($invoice->isEditable())
                <a href="{{ route('invoices.review', $invoice) }}"
                   class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">
                    Rever / Confirmar
                </a>
            @endif
            <a href="{{ route('invoices.index') }}"
               class="rounded-lg border border-white/10 bg-[#151E2D] px-4 py-2 text-sm text-slate-300 hover:bg-white/10">
                ← Lista
            </a>
            <form method="post" action="{{ route('invoices.destroy', $invoice) }}"
                  onsubmit="return confirm('Eliminar esta fatura?')">
                @csrf @method('delete')
                <button class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-400 hover:bg-red-500/20"
                        type="submit">
                    Eliminar
                </button>
            </form>
        </div>
    </x-page-title>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Main content --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Invoice header --}}
            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $invoice->statusBadgeClass() }}">
                        {{ $invoice->statusLabel() }}
                    </span>
                    @if($invoice->status === 'processing')
                        <span class="animate-pulse text-xs text-amber-400">A extrair dados, aguarde...</span>
                    @endif
                </div>

                <dl class="grid gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Fornecedor</dt>
                        <dd class="mt-0.5 font-semibold text-white">{{ $invoice->supplier_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">NIF / NIPC</dt>
                        <dd class="mt-0.5 text-slate-300">{{ $invoice->supplier_tax_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Nº Fatura</dt>
                        <dd class="mt-0.5 text-slate-300">{{ $invoice->invoice_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Moeda</dt>
                        <dd class="mt-0.5 text-slate-300">{{ $invoice->currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Data Fatura</dt>
                        <dd class="mt-0.5 text-slate-300">{{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Vencimento</dt>
                        <dd class="mt-0.5 text-slate-300">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Subtotal</dt>
                        <dd class="mt-0.5 text-slate-300">
                            {{ $invoice->subtotal !== null ? number_format($invoice->subtotal, 2, ',', ' ').' '.$invoice->currency : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">IVA</dt>
                        <dd class="mt-0.5 text-slate-300">
                            {{ $invoice->tax_total !== null ? number_format($invoice->tax_total, 2, ',', ' ').' '.$invoice->currency : '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-white/10 pt-4">
                    <p class="text-xs uppercase tracking-wider text-slate-500">Total</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-400">
                        {{ $invoice->total !== null ? number_format($invoice->total, 2, ',', ' ').' '.$invoice->currency : '—' }}
                    </p>
                </div>
            </div>

            {{-- Items --}}
            @if($invoice->items->isNotEmpty())
                <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
                    <p class="border-b border-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Linhas ({{ $invoice->items->count() }})
                    </p>
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-xs text-slate-500">
                            <tr>
                                <th class="p-3 text-left">Descrição</th>
                                <th class="p-3 text-right">Qtd</th>
                                <th class="p-3 text-right">Preço unit.</th>
                                <th class="p-3 text-right">IVA %</th>
                                <th class="p-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                <tr class="border-t border-white/5">
                                    <td class="p-3 text-slate-300">{{ $item->description ?: '—' }}</td>
                                    <td class="p-3 text-right text-slate-400">{{ number_format($item->quantity, 3, ',', '') }}</td>
                                    <td class="p-3 text-right text-slate-400">
                                        {{ $item->unit_price !== null ? number_format($item->unit_price, 4, ',', '').' '.$invoice->currency : '—' }}
                                    </td>
                                    <td class="p-3 text-right text-slate-400">
                                        {{ $item->tax_rate !== null ? number_format($item->tax_rate, 0, ',', '').'%' : '—' }}
                                    </td>
                                    <td class="p-3 text-right font-semibold text-white">
                                        {{ $item->total !== null ? number_format($item->total, 2, ',', '').' '.$invoice->currency : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Error message --}}
            @if($invoice->status === 'failed' && $invoice->error_message)
                <div class="rounded border border-red-500/20 bg-red-500/10 p-4 text-sm">
                    <p class="mb-1 font-semibold text-red-300">Erro na extração:</p>
                    <p class="font-mono text-xs text-red-400">{{ $invoice->error_message }}</p>
                </div>
            @endif

            {{-- Collapsible raw text --}}
            @if($invoice->raw_extracted_text)
                <details class="rounded border border-white/10 bg-[#151E2D]">
                    <summary class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-300">
                        Texto extraído (raw OCR)
                    </summary>
                    <div class="border-t border-white/10 p-4">
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap font-mono text-xs leading-relaxed text-slate-400">{{ $invoice->raw_extracted_text }}</pre>
                    </div>
                </details>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Ficheiro</p>
                <p class="text-xs text-slate-500">{{ $invoice->mime_type ?? '—' }}</p>
                <p class="mt-1 break-all text-xs text-slate-600">{{ basename($invoice->original_file_path ?? '') }}</p>
                <p class="mt-2 text-xs text-slate-600">Enviado {{ $invoice->created_at->diffForHumans() }}</p>
            </div>

            @php $confidence = $invoice->extracted_data['confidence'] ?? []; @endphp
            @if(!empty($confidence))
                <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Confiança OCR</p>
                    @foreach($confidence as $field => $score)
                        @php $score = (int) $score; @endphp
                        <div class="mb-2">
                            <div class="mb-0.5 flex justify-between text-xs">
                                <span class="text-slate-500">{{ $field }}</span>
                                <span class="{{ $score >= 70 ? 'text-emerald-400' : ($score >= 40 ? 'text-amber-400' : 'text-red-400') }}">
                                    {{ $score }}%
                                </span>
                            </div>
                            <div class="h-1 rounded-full bg-white/10">
                                <div class="h-1 rounded-full {{ $score >= 70 ? 'bg-emerald-500' : ($score >= 40 ? 'bg-amber-500' : 'bg-red-500') }}"
                                     style="width:{{ $score }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($invoice->status === 'processing')
        <script>
            // Auto-refresh while processing
            setTimeout(() => location.reload(), 3000);
        </script>
    @endif
</x-layouts.app>
