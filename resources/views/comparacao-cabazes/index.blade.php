<x-layouts.app title="Margens">
    <x-page-title title="Margens" subtitle="Faturas emitidas menos entradas, por semana" />

    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 sm:grid-cols-[1fr_1fr_auto]">
        <label class="text-sm text-slate-300">Inicio
            <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Fim
            <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <div class="flex items-end">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver margens</button>
        </div>
    </form>

    @php
        $fmt = fn ($v) => number_format((float) $v, 2, ',', ' ').' EUR';
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Faturado</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $fmt($totais['faturado']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Empresas {{ $fmt($totais['empresas']) }} + B2C {{ $fmt($totais['b2c']) }}</p>
        </div>
        <div class="rounded border border-amber-400/30 bg-amber-500/10 p-5">
            <p class="text-sm text-amber-200">Entradas</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $fmt($totais['entradas']) }}</p>
            <p class="mt-1 text-xs text-amber-100/70">Despesas de fornecedores</p>
        </div>
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Margem</p>
            <p class="mt-2 text-2xl font-semibold {{ $totais['margem'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $fmt($totais['margem']) }}</p>
        </div>
        <div class="rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 p-5">
            <p class="text-sm text-blue-200">Margem %</p>
            <p class="mt-2 text-2xl font-semibold text-white">{{ $totais['margem_pct'] === null ? '-' : number_format($totais['margem_pct'], 1, ',', ' ').' %' }}</p>
        </div>
    </div>

    <div class="overflow-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-[#1B2638] text-slate-300">
                <tr>
                    <th class="p-3">Semana</th>
                    <th class="p-3 text-right">Empresas</th>
                    <th class="p-3 text-right">B2C</th>
                    <th class="p-3 text-right">Faturado</th>
                    <th class="p-3 text-right">Entradas</th>
                    <th class="p-3 text-right">Margem</th>
                    <th class="p-3 text-right">Margem %</th>
                </tr>
            </thead>
            <tbody>
                @forelse($semanas as $s)
                    <tr class="border-t border-white/10">
                        <td class="p-3 text-slate-200">
                            <span class="font-semibold text-white">Sem. {{ $s['semana_numero'] }}</span>
                            <span class="ml-1 text-xs text-slate-500">{{ $s['label'] }}</span>
                        </td>
                        <td class="p-3 text-right text-slate-300">{{ $fmt($s['empresas']) }}</td>
                        <td class="p-3 text-right text-slate-300">{{ $fmt($s['b2c']) }}</td>
                        <td class="p-3 text-right font-semibold text-white">{{ $fmt($s['faturado']) }}</td>
                        <td class="p-3 text-right text-amber-200">{{ $fmt($s['entradas']) }}</td>
                        <td class="p-3 text-right font-semibold {{ $s['margem'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $fmt($s['margem']) }}</td>
                        <td class="p-3 text-right text-slate-300">{{ $s['margem_pct'] === null ? '-' : number_format($s['margem_pct'], 1, ',', ' ').' %' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-4 text-slate-400">Sem dados no periodo escolhido.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($semanas->isNotEmpty())
                <tfoot class="border-t border-white/20 bg-[#1B2638]">
                    <tr>
                        <td class="p-3 font-semibold text-white">Total</td>
                        <td class="p-3 text-right text-slate-200">{{ $fmt($totais['empresas']) }}</td>
                        <td class="p-3 text-right text-slate-200">{{ $fmt($totais['b2c']) }}</td>
                        <td class="p-3 text-right font-semibold text-white">{{ $fmt($totais['faturado']) }}</td>
                        <td class="p-3 text-right text-amber-200">{{ $fmt($totais['entradas']) }}</td>
                        <td class="p-3 text-right font-semibold {{ $totais['margem'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $fmt($totais['margem']) }}</td>
                        <td class="p-3 text-right text-slate-200">{{ $totais['margem_pct'] === null ? '-' : number_format($totais['margem_pct'], 1, ',', ' ').' %' }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <p class="mt-4 text-xs text-slate-500">
        Vendas contam faturas efetivamente emitidas no Moloni (empresas e B2C). As entradas sao as despesas de fornecedores, pela data da despesa.
    </p>
</x-layouts.app>
