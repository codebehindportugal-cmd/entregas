<x-layouts.app title="Margens">
    <x-page-title title="Margens" subtitle="Comparacao entre custo e vendas" />

    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <div class="grid gap-3 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <label class="text-sm text-slate-300">Lista semanal
                <select name="lista_cabaz_id" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($listas as $opcao)
                        <option value="{{ $opcao->id }}" @selected($lista?->id === $opcao->id)>{{ $opcao->tituloFormatado() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Inicio empresas
                <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-300">Fim empresas
                <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver comparacao</button>
            </div>
        </div>
        @if($lista)
            <p class="mt-3 text-xs text-slate-400">Pedido na semana anterior pela empresa. Custos calculados pela lista selecionada; vendas calculadas com o preco por peca definido em cada empresa.</p>
        @endif
    </form>

    <h2 class="mb-3 text-lg font-semibold text-slate-900">Cabazes de empresas</h2>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Cabazes empresas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $resumo['cabazes'] }}</p>
        </div>
        <div class="rounded border border-rose-400/30 bg-rose-500/10 p-5">
            <p class="text-sm text-rose-200">Custo</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumo['custo'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Venda</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumo['venda'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 p-5">
            <p class="text-sm text-blue-200">Margem</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumo['margem'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-5">
            <p class="text-sm text-amber-200">Margem %</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $resumo['margem_percentagem'] !== null ? number_format($resumo['margem_percentagem'], 1, ',', ' ').'%' : '-' }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
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
                    <tr class="border-t border-white/10">
                        <td class="p-3 text-white">
                            {{ $linha['empresa']->empresa }}
                            <span class="block text-xs text-slate-500">{{ $linha['empresa']->sucursal ?: 'Sem sucursal' }}</span>
                        </td>
                        <td class="p-3 text-slate-300">{{ $linha['tipo_label'] }}</td>
                        <td class="p-3 text-slate-300">{{ $linha['quantidade'] }}</td>
                        <td class="p-3 text-slate-300">{{ number_format($linha['pecas_cabaz'], 2, ',', ' ') }}</td>
                        <td class="p-3 text-slate-300">{{ $linha['preco_peca'] !== null ? number_format($linha['preco_peca'], 4, ',', ' ').' EUR' : 'Sem preco' }}</td>
                        <td class="p-3 text-rose-100">{{ number_format($linha['custo_cabaz'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 text-emerald-100">{{ $linha['venda_cabaz'] !== null ? number_format($linha['venda_cabaz'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3 font-semibold text-rose-100">{{ number_format($linha['custo'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 font-semibold text-emerald-100">{{ $linha['venda'] !== null ? number_format($linha['venda'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3 font-semibold {{ ($linha['margem'] ?? 0) >= 0 ? 'text-emerald-100' : 'text-rose-100' }}">{{ $linha['margem'] !== null ? number_format($linha['margem'], 2, ',', ' ').' EUR' : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-4 text-slate-400">Sem cabazes de empresas para comparar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 mt-8 text-lg font-semibold text-slate-900">Empresas fruta individual</h2>
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Empresas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $resumoEmpresas['empresas'] }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $resumoEmpresas['entregas'] }} entregas</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Pecas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $resumoEmpresas['pecas'] }}</p>
        </div>
        <div class="rounded border border-rose-400/30 bg-rose-500/10 p-5">
            <p class="text-sm text-rose-200">Custo</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumoEmpresas['custo'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Venda</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumoEmpresas['venda'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 p-5">
            <p class="text-sm text-blue-200">Margem</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($resumoEmpresas['margem'], 2, ',', ' ') }} EUR</p>
            <p class="mt-1 text-xs text-blue-100/80">{{ $resumoEmpresas['margem_percentagem'] !== null ? number_format($resumoEmpresas['margem_percentagem'], 1, ',', ' ').'%' : '-' }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
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
                    <tr class="border-t border-white/10">
                        <td class="p-3 text-white">
                            {{ $linha['empresa']->empresa }}
                            <span class="block text-xs text-slate-500">{{ $linha['empresa']->sucursal ?: 'Sem sucursal' }}</span>
                        </td>
                        <td class="p-3 text-slate-300">{{ $linha['entregas'] }}</td>
                        <td class="p-3 text-slate-300">{{ $linha['pecas'] }}</td>
                        <td class="p-3 text-slate-300">{{ $linha['preco_peca'] !== null ? number_format($linha['preco_peca'], 4, ',', ' ').' EUR' : 'Sem preco' }}</td>
                        <td class="p-3 font-semibold text-rose-100">{{ number_format($linha['custo'], 2, ',', ' ') }} EUR</td>
                        <td class="p-3 font-semibold text-emerald-100">{{ $linha['venda'] !== null ? number_format($linha['venda'], 2, ',', ' ').' EUR' : '-' }}</td>
                        <td class="p-3 font-semibold {{ ($linha['margem'] ?? 0) >= 0 ? 'text-emerald-100' : 'text-rose-100' }}">{{ $linha['margem'] !== null ? number_format($linha['margem'], 2, ',', ' ').' EUR' : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-4 text-slate-400">Sem empresas com fruta individual neste periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
