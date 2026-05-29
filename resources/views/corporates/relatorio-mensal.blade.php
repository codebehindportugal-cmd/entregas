<x-layouts.app title="Relatorio mensal - {{ $corporate->empresa }}">
    <style>
        @media print {
            aside,
            header,
            .no-print {
                display: none !important;
            }

            body,
            main {
                background: #fff !important;
                color: #111 !important;
            }

            main {
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-surface {
                border: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #111 !important;
            }

            .print-table th,
            .print-table td {
                color: #111 !important;
                border-color: #ddd !important;
            }
        }
    </style>

    <x-page-title title="Relatorio mensal" subtitle="{{ $corporate->empresa }} - {{ $inicio->format('m/Y') }}">
        <div class="no-print flex flex-wrap gap-2">
            <a href="{{ route('corporates.show', $corporate) }}" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200">Voltar</a>
            <button type="button" onclick="window.print()" class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Imprimir / Exportar PDF</button>
        </div>
    </x-page-title>

    <div class="no-print mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <form method="get" action="{{ route('corporates.relatorio-mensal', $corporate) }}" class="flex flex-wrap items-end gap-3">
            <label class="block text-sm text-slate-300">Mes
                <input name="mes" type="month" value="{{ $mes }}" class="mt-1 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver relatorio</button>
        </form>
    </div>

    <section class="print-surface rounded border border-white/10 bg-[#151E2D] p-5">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-white">{{ $corporate->empresa }}</h2>
            @if($corporate->sucursal)
                <p class="text-sm text-slate-400">{{ $corporate->sucursal }}</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="print-table w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-white/10 text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-3 pr-4">Data</th>
                        <th class="py-3 pr-4">Dia</th>
                        <th class="py-3 pr-4">Estado</th>
                        <th class="py-3 pr-4">Hora</th>
                        <th class="py-3">Nota</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($linhas as $linha)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-white">{{ $linha['data']->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-slate-300">{{ $linha['dia'] }}</td>
                            <td class="py-3 pr-4">
                                @if($linha['estado'] === 'entregue')
                                    <span class="rounded bg-green-500/15 px-2 py-1 text-xs font-semibold text-green-200">Entregue</span>
                                @elseif($linha['estado'] === 'falhou')
                                    <span class="rounded bg-red-500/15 px-2 py-1 text-xs font-semibold text-red-200">Falhou</span>
                                @elseif($linha['estado'] === 'nao_entregamos')
                                    <span class="rounded bg-slate-500/20 px-2 py-1 text-xs font-semibold text-slate-200">! Nao entregamos</span>
                                @else
                                    <span class="rounded bg-yellow-500/15 px-2 py-1 text-xs font-semibold text-yellow-200">Sem registo</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-300">{{ $linha['hora_entrega']?->format('H:i') ?: '-' }}</td>
                            <td class="whitespace-pre-line py-3 text-slate-300">{{ $linha['nota'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400">Nao existem dias de entrega previstos neste mes.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-white/10 text-sm font-semibold text-white">
                    <tr>
                        <td colspan="5" class="py-4">
                            {{ $totais['entregue'] }} entregas feitas,
                            {{ $totais['falhou'] }} falhas,
                            {{ $totais['nao_entregamos'] }} nao entregamos,
                            {{ $totais['sem_registo'] }} sem registo
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</x-layouts.app>
