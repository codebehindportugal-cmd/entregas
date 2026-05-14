<x-layouts.app title="Margens">
    <x-page-title title="Margens" subtitle="Comparacao entre custo e vendas" />

    <form method="get" class="mb-6 rounded border border-emerald-900/10 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700">Lista semanal
                <select name="lista_cabaz_id" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                    @foreach($listas as $opcao)
                        <option value="{{ $opcao->id }}" @selected($lista?->id === $opcao->id)>{{ $opcao->tituloFormatado() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Inicio empresas
                <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Fim empresas
                <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver comparacao</button>
            </div>
        </div>
        @if($lista)
            <p class="mt-3 text-xs text-slate-500">Pedido na semana anterior pela empresa. Custos calculados pela lista selecionada; vendas calculadas com o preco por peca definido em cada empresa.</p>
        @endif
    </form>

    <h2 class="mb-3 text-lg font-semibold text-slate-900">Cabazes de empresas</h2>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Cabazes empresas</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $resumo['cabazes'] }}</p>
        </div>
        <div class="rounded border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-rose-700">Custo</p>
            <p class="mt-2 text-3xl font-semibold text-rose-950">{{ number_format($resumo['custo'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-emerald-700">Venda</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ number_format($resumo['venda'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-blue-700">Margem</p>
            <p class="mt-2 text-3xl font-semibold text-blue-950">{{ number_format($resumo['margem'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-amber-700">Margem %</p>
            <p class="mt-2 text-3xl font-semibold text-amber-950">{{ $resumo['margem_percentagem'] !== null ? number_format($resumo['margem_percentagem'], 1, ',', ' ').'%' : '-' }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="p-3">Empresa</th>
                    <th class="p-3">Cabaz</th>
                    <th class="p-3">Qtd.</th>
                    <th class="p-3">Pecas/cabaz</th>
                    <th class="p-3">Preco/peca</th>
                    <th class="p-3">Custo/cabaz</th>
                    <th class="p-3">Venda/cabaz</th>
                    <th class="p-3">Custo</th>
                    <th class="p-3">Venda</th>
                    <th class="p-3">Margem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($linhas as $linha)
                    <tr>
                        <td class="p-3 text-slate-950">
                            {{ $linha['empresa']->empresa }}
                            <span class="block text-xs font-normal text-slate-500">{{ $linha['empresa']->sucursal ?: 'Sem sucursal' }}</span>
                        </td>
                        <td class="p-3 text-slate-700">{{ $linha['tipo_label'] }}</td>
                        <td class="p-3 text-slate-700">{{ $linha['quantidade'] }}</td>
                        <td class="p-3 text-slate-700">{{ number_format($linha['pecas_cabaz'], 2, ',', ' ') }}</td>
                        <td class="p-3 text-slate-700">{{ $linha['preco_peca'] !== null ? number_format($linha['preco_peca'], 4, ',', ' ').' EUR' : 'Sem preco' }}</td>
                        <td class="p-3 text-rose-800">{{ number_format($linha['custo_cabaz'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 text-emerald-800">{{ $linha['venda_cabaz'] !== null ? number_format($linha['venda_cabaz'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3 font-semibold text-rose-900">{{ number_format($linha['custo'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 font-semibold text-emerald-900">{{ $linha['venda'] !== null ? number_format($linha['venda'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3">
                            <span class="rounded px-2.5 py-1 text-xs font-bold {{ ($linha['margem'] ?? 0) >= 0 ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">{{ $linha['margem'] !== null ? number_format($linha['margem'], 2, ',', ' ').' EUR' : '-' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-4 text-slate-500">Sem cabazes de empresas para comparar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 mt-8 text-lg font-semibold text-slate-900">Empresas fruta individual</h2>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Empresas</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $resumoEmpresas['empresas'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $resumoEmpresas['entregas'] }} entregas</p>
        </div>
        <div class="rounded border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Pecas</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $resumoEmpresas['pecas'] }}</p>
        </div>
        <div class="rounded border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-rose-700">Custo</p>
            <p class="mt-2 text-3xl font-semibold text-rose-950">{{ number_format($resumoEmpresas['custo'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-emerald-700">Venda</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ number_format($resumoEmpresas['venda'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-blue-700">Margem</p>
            <p class="mt-2 text-3xl font-semibold text-blue-950">{{ number_format($resumoEmpresas['margem'], 2, ',', ' ') }} EUR</p>
            <p class="mt-1 text-xs text-blue-700">{{ $resumoEmpresas['margem_percentagem'] !== null ? number_format($resumoEmpresas['margem_percentagem'], 1, ',', ' ').'%' : '-' }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="p-3">Empresa</th>
                    <th class="p-3">Entregas</th>
                    <th class="p-3">Pecas</th>
                    <th class="p-3">Preco/peca</th>
                    <th class="p-3">Custo</th>
                    <th class="p-3">Venda</th>
                    <th class="p-3">Margem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($linhasEmpresas as $linha)
                    <tr>
                        <td class="p-3 text-slate-950">
                            {{ $linha['empresa']->empresa }}
                            <span class="block text-xs font-normal text-slate-500">{{ $linha['empresa']->sucursal ?: 'Sem sucursal' }}</span>
                        </td>
                        <td class="p-3 text-slate-700">{{ $linha['entregas'] }}</td>
                        <td class="p-3 text-slate-700">{{ $linha['pecas'] }}</td>
                        <td class="p-3 text-slate-700">{{ $linha['preco_peca'] !== null ? number_format($linha['preco_peca'], 4, ',', ' ').' EUR' : 'Sem preco' }}</td>
                        <td class="p-3 font-semibold text-rose-900">{{ number_format($linha['custo'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 font-semibold text-emerald-900">{{ $linha['venda'] !== null ? number_format($linha['venda'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3">
                            <span class="rounded px-2.5 py-1 text-xs font-bold {{ ($linha['margem'] ?? 0) >= 0 ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">{{ $linha['margem'] !== null ? number_format($linha['margem'], 2, ',', ' ').' EUR' : '-' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-4 text-slate-500">Sem empresas com fruta individual neste periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
