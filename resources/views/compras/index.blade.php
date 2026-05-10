<x-layouts.app title="Compras">
    <x-page-title title="Compras" subtitle="Necessidades em kg por dia" />

    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach(['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mes', 'personalizado' => 'Personalizado'] as $value => $label)
                <label class="cursor-pointer rounded px-3 py-2 text-sm font-medium {{ $periodo === $value ? 'bg-[#3B82F6] text-white' : 'bg-white/10 text-slate-300' }}">
                    <input type="radio" name="periodo" value="{{ $value }}" class="sr-only" @checked($periodo === $value) onchange="this.form.submit()">
                    {{ $label }}
                </label>
            @endforeach
        </div>
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
                @if(! in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true))
                    <label class="text-xs text-slate-400">{{ $label }} kg/peca
                        <input name="pesos[{{ $key }}]" type="number" min="0" step="0.01" value="{{ number_format($pesos[$key], 2, '.', '') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                    </label>
                @endif
            @endforeach
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
        <div class="rounded border border-rose-400/30 bg-rose-500/10 p-5">
            <p class="text-sm text-rose-200">Custo estimado</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($total_custo, 2, ',', ' ') }} €</p>
            <p class="mt-1 text-xs text-rose-100/80">{{ $tabelas_precos->isNotEmpty() ? $tabelas_precos->pluck('fornecedor')->unique()->join(', ') : 'Sem tabela ativa' }}</p>
        </div>
    </div>

    <form method="post" action="{{ route('compras.precos.update') }}" class="mb-6">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach($labels as $key => $label)
            @php($selectedPrecoItemId = old('precos.'.$key, $mapeamentos_precos->get($key)?->tabela_preco_item_id))
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <p class="text-sm text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ number_format($totais_kg[$key] ?? 0, 2, ',', ' ') }} kg</p>
                <p class="mt-1 text-xs text-slate-500">{{ in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true) ? 'kg direto' : (($totais_pecas[$key] ?? 0).' pecas') }}</p>
                <p class="mt-3 text-sm font-semibold text-rose-100">{{ number_format($totais_custos[$key] ?? 0, 2, ',', ' ') }} €</p>
                <p class="mt-1 min-h-10 text-xs text-slate-500">
                    @if(isset($precos[$key]))
                        {{ number_format($precos[$key]['preco'], 2, ',', ' ') }} €/kg · {{ $precos[$key]['produto'] }}@if(! empty($precos[$key]['fornecedor'])) · {{ $precos[$key]['fornecedor'] }}@endif
                    @else
                        sem preco
                    @endif
                </p>
                <label class="mt-3 block text-xs text-slate-400">Produto associado
                    <select name="precos[{{ $key }}]" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-2 text-xs text-white">
                        <option value="">Automatico</option>
                        @foreach($preco_itens_disponiveis as $precoItem)
                            <option value="{{ $precoItem->id }}" @selected((string) $selectedPrecoItemId === (string) $precoItem->id)>
                                {{ $precoItem->produto }} - {{ number_format((float) $precoItem->preco_kg, 2, ',', ' ') }} EUR/kg - {{ $precoItem->tabelaPreco?->fornecedor }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endforeach
        </div>
        <div class="mt-3 flex justify-end">
            <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Guardar associacoes</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
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
                    <th class="p-3">Custo</th>
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
                                <p class="text-xs text-slate-500">{{ in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true) ? 'kg direto' : (($linha['pecas'][$key] ?? 0).' pecas') }}</p>
                                <p class="mt-1 text-xs font-semibold text-rose-100">{{ number_format($linha['custos'][$key] ?? 0, 2, ',', ' ') }} €</p>
                            </td>
                        @endforeach
                        <td class="p-3 font-semibold text-white">{{ number_format($linha['total_kg'], 2, ',', ' ') }} kg</td>
                        <td class="p-3 font-semibold text-rose-100">{{ number_format($linha['total_custo'], 2, ',', ' ') }} €</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($labels) + 5 }}" class="p-4 text-slate-400">Nao existem dias uteis no intervalo escolhido.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
