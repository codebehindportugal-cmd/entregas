<x-layouts.app title="Compras">
    <x-page-title title="Compras" subtitle="Necessidades em kg por dia" />

    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
            <label class="text-sm text-slate-300">Inicio
                <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-300">Fim
                <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Calcular</button>
                <a href="{{ route('compras.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach($labels as $key => $label)
                @if($key !== 'uvas')
                    <label class="text-xs text-slate-400">{{ $label }} kg/peca
                        <input name="pesos[{{ $key }}]" type="number" min="0" step="0.01" value="{{ number_format($pesos[$key], 2, '.', '') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                    </label>
                @endif
            @endforeach
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 p-5">
            <p class="text-sm text-blue-200">Kg totais</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($total_kg, 2, ',', ' ') }} kg</p>
        </div>
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Pecas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $total_pecas }}</p>
        </div>
        <div class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-5">
            <p class="text-sm text-amber-200">Caixas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $total_caixas }}</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Entregas corporate</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $total_clientes }}</p>
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach($labels as $key => $label)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <p class="text-sm text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ number_format($totais_kg[$key] ?? 0, 2, ',', ' ') }} kg</p>
                <p class="mt-1 text-xs text-slate-500">{{ $key === 'uvas' ? 'kg direto' : (($totais_pecas[$key] ?? 0).' pecas') }}</p>
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Dia</th>
                    <th class="p-3">Clientes</th>
                    <th class="p-3">Caixas</th>
                    @foreach($labels as $label)
                        <th class="p-3">{{ $label }}</th>
                    @endforeach
                    <th class="p-3">Total kg</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dias as $linha)
                    <tr class="border-t border-white/10 align-top">
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $linha['dia'] }}</p>
                            <p class="text-xs text-slate-400">{{ $linha['data']->format('d/m/Y') }}</p>
                        </td>
                        <td class="p-3 text-slate-300">{{ $linha['clientes'] }}</td>
                        <td class="p-3 text-emerald-200">{{ $linha['caixas'] }}</td>
                        @foreach(array_keys($labels) as $key)
                            <td class="p-3 text-slate-300">
                                <p class="font-semibold text-white">{{ number_format($linha['kg'][$key] ?? 0, 2, ',', ' ') }} kg</p>
                                <p class="text-xs text-slate-500">{{ $key === 'uvas' ? 'kg direto' : (($linha['pecas'][$key] ?? 0).' pecas') }}</p>
                            </td>
                        @endforeach
                        <td class="p-3 font-semibold text-white">{{ number_format($linha['total_kg'], 2, ',', ' ') }} kg</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-4 text-slate-400">Nao existem dias uteis no intervalo escolhido.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
