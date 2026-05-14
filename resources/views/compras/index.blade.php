<x-layouts.app title="Compras">
    <x-page-title title="Compras" subtitle="Necessidades em kg por dia" />

    <form method="get" class="mb-6 rounded border border-emerald-900/10 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach(['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mes', 'personalizado' => 'Personalizado'] as $value => $label)
                <label class="cursor-pointer rounded border px-3 py-2 text-sm font-medium {{ $periodo === $value ? 'border-[#3B82F6] bg-[#3B82F6] text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                    <input type="radio" name="periodo" value="{{ $value }}" class="sr-only" @checked($periodo === $value) onchange="this.form.submit()">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700">Inicio
                <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Fim
                <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Calcular</button>
                <a href="{{ route('compras.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach($labels as $key => $label)
                @if(! in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true))
                    <label class="text-xs font-medium text-slate-600">{{ $label }} kg/peca
                        <input name="pesos[{{ $key }}]" type="number" min="0" step="0.01" value="{{ number_format($pesos[$key], 2, '.', '') }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                    </label>
                @endif
            @endforeach
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-blue-700">Kg totais</p>
            <p class="mt-2 text-3xl font-semibold text-blue-950">{{ number_format($total_kg, 2, ',', ' ') }} kg</p>
        </div>
        <div class="rounded border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-emerald-700">Pecas</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ $total_pecas }}</p>
        </div>
        <div class="rounded border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-amber-700">Caixas</p>
            <p class="mt-2 text-3xl font-semibold text-amber-950">{{ $total_caixas }}</p>
        </div>
        <div class="rounded border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Entregas corporate</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $total_clientes }}</p>
        </div>
        <div class="rounded border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-rose-700">Custo estimado</p>
            <p class="mt-2 text-3xl font-semibold text-rose-950">{{ number_format($total_custo, 2, ',', ' ') }} EUR</p>
            <p class="mt-1 text-xs text-rose-700">{{ $tabelas_precos->isNotEmpty() ? $tabelas_precos->pluck('fornecedor')->unique()->join(', ') : 'Sem tabela ativa' }}</p>
        </div>
    </div>

    <form method="post" action="{{ route('compras.precos.update') }}" class="mb-6">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @foreach($labels as $key => $label)
                @php($selectedPrecoItemId = old('precos.'.$key, $mapeamentos_precos->get($key)?->tabela_preco_item_id))
                <div class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($totais_kg[$key] ?? 0, 2, ',', ' ') }} kg</p>
                    <p class="mt-1 text-xs text-slate-500">{{ in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true) ? 'kg direto' : (($totais_pecas[$key] ?? 0).' pecas') }}</p>
                    <p class="mt-3 text-sm font-semibold text-rose-800">{{ number_format($totais_custos[$key] ?? 0, 2, ',', ' ') }} EUR</p>
                    <p class="mt-1 min-h-10 text-xs text-slate-600">
                        @if(isset($precos[$key]))
                            {{ number_format($precos[$key]['preco'], 2, ',', ' ') }} EUR/kg - {{ $precos[$key]['produto'] }}@if(! empty($precos[$key]['fornecedor'])) - {{ $precos[$key]['fornecedor'] }}@endif
                        @else
                            sem preco
                        @endif
                    </p>
                    <label class="mt-3 block text-xs font-medium text-slate-600">Produto associado
                        <select name="precos[{{ $key }}]" class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-2 text-xs text-slate-950 shadow-sm">
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

    <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
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
                    <tr class="align-top">
                        <td class="p-3">
                            <p class="font-semibold text-slate-950">{{ $linha['dia'] }}</p>
                            <p class="text-xs font-normal text-slate-500">{{ $linha['data']->format('d/m/Y') }}</p>
                        </td>
                        <td class="p-3 text-slate-700">{{ $linha['clientes'] }}</td>
                        <td class="p-3 font-semibold text-emerald-800">{{ $linha['caixas'] }}</td>
                        @foreach(array_keys($labels) as $key)
                            <td class="p-3 text-slate-700">
                                <p class="font-semibold text-slate-950">{{ number_format($linha['kg'][$key] ?? 0, 2, ',', ' ') }} kg</p>
                                <p class="text-xs text-slate-500">{{ in_array($key, \App\Services\ComprasService::PRODUTOS_KG, true) ? 'kg direto' : (($linha['pecas'][$key] ?? 0).' pecas') }}</p>
                                <p class="mt-1 text-xs font-semibold text-rose-800">{{ number_format($linha['custos'][$key] ?? 0, 2, ',', ' ') }} EUR</p>
                            </td>
                        @endforeach
                        <td class="p-3 font-semibold text-slate-950">{{ number_format($linha['total_kg'], 2, ',', ' ') }} kg</td>
                        <td class="p-3">
                            <span class="rounded bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-900">{{ number_format($linha['total_custo'], 2, ',', ' ') }} EUR</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($labels) + 5 }}" class="p-4 text-slate-500">Nao existem dias uteis no intervalo escolhido.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
