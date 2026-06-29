<x-layouts.app title="Faturas OCR">
    <x-page-title title="Faturas OCR" subtitle="Upload e extração automática de faturas">
        <a href="{{ route('invoices.upload') }}"
           class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">
            + Nova fatura
        </a>
    </x-page-title>

    {{-- Desktop table --}}
    <div class="hidden overflow-x-auto rounded border border-white/10 bg-[#151E2D] lg:block">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-xs uppercase tracking-wider text-slate-400">
                <tr>
                    <th class="p-3">Fornecedor</th>
                    <th class="p-3">Nº Fatura</th>
                    <th class="p-3">Data</th>
                    <th class="p-3 text-right">Total</th>
                    <th class="p-3 text-center">Estado</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr class="border-t border-white/10 hover:bg-white/5">
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $invoice->supplier_name ?? '—' }}</p>
                            @if($invoice->supplier_tax_number)
                                <p class="text-xs text-slate-400">NIF {{ $invoice->supplier_tax_number }}</p>
                            @endif
                        </td>
                        <td class="p-3 text-slate-300">{{ $invoice->invoice_number ?? '—' }}</td>
                        <td class="whitespace-nowrap p-3 text-slate-400">
                            {{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="p-3 text-right font-semibold text-white">
                            {{ $invoice->total !== null ? number_format($invoice->total, 2, ',', ' ').' '.$invoice->currency : '—' }}
                        </td>
                        <td class="p-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->statusBadgeClass() }}">
                                {{ $invoice->statusLabel() }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-3 text-sm">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-sky-400 hover:text-sky-300">Ver</a>
                                @if($invoice->isEditable())
                                    <a href="{{ route('invoices.review', $invoice) }}" class="text-emerald-400 hover:text-emerald-300">Rever</a>
                                @endif
                                <form method="post" action="{{ route('invoices.destroy', $invoice) }}"
                                      onsubmit="return confirm('Eliminar esta fatura?')">
                                    @csrf @method('delete')
                                    <button class="text-red-400 hover:text-red-300" type="submit">Remover</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            Nenhuma fatura ainda.
                            <a href="{{ route('invoices.upload') }}" class="text-emerald-400 underline">Enviar primeira fatura</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="space-y-3 lg:hidden">
        @forelse($invoices as $invoice)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-white">{{ $invoice->supplier_name ?? '(sem fornecedor)' }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $invoice->invoice_number ?? '—' }} · {{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                    <p class="shrink-0 text-base font-bold text-white">
                        {{ $invoice->total !== null ? number_format($invoice->total, 2, ',', ' ').' '.$invoice->currency : '—' }}
                    </p>
                </div>
                <div class="mt-2">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->statusBadgeClass() }}">
                        {{ $invoice->statusLabel() }}
                    </span>
                </div>
                <div class="mt-3 flex gap-3 text-sm">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-sky-400">Ver</a>
                    @if($invoice->isEditable())
                        <a href="{{ route('invoices.review', $invoice) }}" class="text-emerald-400">Rever</a>
                    @endif
                    <form method="post" action="{{ route('invoices.destroy', $invoice) }}"
                          onsubmit="return confirm('Eliminar?')">
                        @csrf @method('delete')
                        <button class="text-red-400" type="submit">Remover</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded border border-white/10 bg-[#151E2D] p-6 text-center text-slate-400">
                Nenhuma fatura.
                <a href="{{ route('invoices.upload') }}" class="text-emerald-400 underline">Enviar</a>
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
