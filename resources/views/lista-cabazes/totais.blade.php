<x-layouts.app title="Totais a comprar">
    <x-page-title title="Totais a comprar" subtitle="{{ $listaCabaz->tituloFormatado() }}">
        <button type="button" onclick="window.print()" class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white print:hidden">Imprimir</button>
    </x-page-title>

    <style>
        @media print {
            aside, header, .print\:hidden { display: none !important; }
            main { margin: 0 !important; }
            body { background: white !important; color: black !important; }
        }
    </style>

    @php
        $precosPorCabaz = \App\Models\ListaCabaz::precosPorCabaz();
        $custoMaxPorCabaz = \App\Models\ListaCabaz::custoMaxPorCabaz();
    @endphp

    {{-- Cards por tipo --}}
    <div class="mb-6 grid gap-3 md:grid-cols-4">
        @foreach($tipos as $tipo => $label)
            @php
                $totalTipo = ($contagens[$tipo]['subscritores'] ?? 0) + ($contagens[$tipo]['empresas'] ?? 0);
                $custoTipo = $custoPorTipo[$tipo] ?? 0;
                $precoTipo = $precosPorCabaz[$tipo] ?? 0;
                $custoMax  = $custoMaxPorCabaz[$tipo] ?? 0;
                $margem    = $precoTipo - $custoTipo;
                $margemPct = $precoTipo > 0 ? round(($margem / $precoTipo) * 100, 1) : null;
                $ok        = $custoTipo <= $custoMax;
            @endphp
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-slate-400">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-white">{{ $totalTipo }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $contagens[$tipo]['subscritores'] ?? 0 }} sub + {{ $contagens[$tipo]['empresas'] ?? 0 }} emp</p>
                    </div>
                    @if($margemPct !== null)
                        <span class="mt-1 rounded px-2 py-0.5 text-xs font-bold {{ $margemPct >= 60 ? 'bg-emerald-900/60 text-emerald-300' : 'bg-rose-900/60 text-rose-300' }}">
                            {{ number_format($margemPct, 0) }}%
                        </span>
                    @endif
                </div>
                @if($precoTipo > 0)
                    <div class="mt-3 border-t border-white/10 pt-3 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Custo/cabaz</span>
                            <span class="{{ $ok ? 'text-slate-300' : 'text-rose-400 font-semibold' }}">{{ number_format($custoTipo, 2, ',', ' ') }} €</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Max custo (60%)</span>
                            <span class="text-slate-500">{{ number_format($custoMax, 2, ',', ' ') }} €</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Venda/cabaz</span>
                            <span class="text-emerald-400">{{ number_format($precoTipo, 2, ',', ' ') }} €</span>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Produto</th>
                    <th class="p-3">Categoria</th>
                    @foreach($tipos as $label)
                        <th class="p-3">{{ $label }} qtd.</th>
                        <th class="p-3">{{ $label }} €</th>
                    @endforeach
                    <th class="p-3">Total qtd.</th>
                    <th class="p-3">Total €</th>
                </tr>
            </thead>
            <tbody>
                @forelse($totais as $linha)
                    <tr class="border-t border-white/10">
                        <td class="p-3 font-semibold text-white">{{ $linha['produto'] }}</td>
                        <td class="p-3 text-slate-300">{{ $linha['categoria'] ?: '-' }}</td>
                        @foreach($tipos as $tipo => $label)
                            <td class="p-3 text-slate-300">{{ number_format($linha['por_tipo'][$tipo]['quantidade'] ?? 0, 3, ',', ' ') }} {{ $linha['unidade'] }}</td>
                            <td class="p-3 text-slate-300">{{ number_format($linha['por_tipo'][$tipo]['custo'] ?? 0, 2, ',', ' ') }} €</td>
                        @endforeach
                        <td class="p-3 font-semibold text-white">{{ number_format($linha['total'], 3, ',', ' ') }} {{ $linha['unidade'] }}</td>
                        <td class="p-3 font-semibold text-white">{{ number_format($linha['custo_total'], 2, ',', ' ') }} €</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ (count($tipos) * 2) + 4 }}" class="p-4 text-slate-400">Sem produtos para calcular.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="text-lg font-semibold text-white">Custo total geral de compras</h2>
        <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($totais->sum('custo_total'), 2, ',', ' ') }} €</p>
    </div>
</x-layouts.app>
