<x-layouts.app title="Editar lista semanal">
    <x-page-title title="{{ $listaCabaz->tituloFormatado() }}" subtitle="Semana {{ $listaCabaz->semana_numero }} - {{ \App\Models\ListaCabaz::meses()[$listaCabaz->mes] }} {{ $listaCabaz->ano }}">
        <a href="{{ route('lista-cabazes.totais', $listaCabaz) }}" class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Ver totais a comprar</a>
    </x-page-title>

    <form method="post" action="{{ route('lista-cabazes.update', $listaCabaz) }}" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        @csrf
        @method('put')
        <div class="grid gap-3 md:grid-cols-[2fr_1fr_auto]">
            <label class="text-sm text-slate-300">Descricao
                <input name="descricao" value="{{ old('descricao', $listaCabaz->descricao) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-300">Estado
                <select name="estado" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    <option value="rascunho" @selected($listaCabaz->estado === 'rascunho')>Rascunho</option>
                    <option value="publicada" @selected($listaCabaz->estado === 'publicada')>Publicada</option>
                </select>
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
            </div>
        </div>
    </form>

    <div class="mb-5 flex flex-wrap gap-2" data-lista-tabs>
        @foreach($tipos as $tipo => $label)
            <button type="button" data-lista-tab="{{ $tipo }}" class="rounded px-4 py-2 text-sm font-semibold">
                {{ $label }} - {{ $contagens[$tipo]['subscritores'] ?? 0 }} subscritores + {{ $contagens[$tipo]['empresas'] ?? 0 }} empresas
            </button>
        @endforeach
    </div>

    @foreach($tipos as $tipo => $label)
        <section data-lista-panel="{{ $tipo }}" class="hidden rounded border border-white/10 bg-[#151E2D] p-5">
            <h2 class="text-lg font-semibold text-white">{{ $label }}</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white/5 text-slate-400">
                        <tr>
                            <th class="p-3">Produto</th>
                            <th class="p-3">Categoria</th>
                            <th class="p-3">Quantidade</th>
                            <th class="p-3">Preco</th>
                            <th class="p-3">Custo</th>
                            <th class="p-3">Ordem</th>
                            <th class="p-3 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($itensPorTipo[$tipo] ?? collect()) as $item)
                            <tr class="border-t border-white/10">
                                <form method="post" action="{{ route('lista-cabazes.itens.update', $item) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="cabaz_tipo" value="{{ $tipo }}">
                                    <td class="p-2"><input name="produto" value="{{ $item->produto }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                    <td class="p-2"><input name="categoria" value="{{ $item->categoria }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                    <td class="p-2">
                                        <div class="flex gap-2">
                                            <input name="quantidade" type="number" min="0.001" step="0.001" value="{{ $item->quantidade }}" class="w-28 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                            <input name="unidade" value="{{ $item->unidade }}" class="w-20 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <select name="tabela_preco_item_id" class="mb-2 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-xs text-white">
                                            <option value="">Preco manual</option>
                                            @foreach($precoItens as $precoItem)
                                                <option value="{{ $precoItem->id }}" @selected($item->tabela_preco_item_id === $precoItem->id)>{{ $precoItem->produto }} - {{ $precoItem->precoFormatado() }}</option>
                                            @endforeach
                                        </select>
                                        <input name="preco_unitario" type="number" min="0" step="0.0001" value="{{ $item->preco_unitario }}" placeholder="Preco manual" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-xs text-white">
                                    </td>
                                    <td class="p-2 font-semibold text-white">{{ $item->custoUnitario() !== null ? number_format($item->custoUnitario(), 2, ',', ' ').' €' : '-' }}</td>
                                    <td class="p-2"><input name="ordem" type="number" min="0" value="{{ $item->ordem }}" class="w-24 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                    <td class="p-2 text-right">
                                        <button class="rounded bg-[#22C55E]/20 px-3 py-2 text-xs font-semibold text-emerald-200">Guardar</button>
                                </form>
                                        <form method="post" action="{{ route('lista-cabazes.itens.destroy', $item) }}" class="inline-block" onsubmit="return confirm('Remover este produto?');">
                                            @csrf
                                            @method('delete')
                                            <button class="rounded bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Apagar</button>
                                        </form>
                                    </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-slate-400">Sem produtos neste tipo de cabaz.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 rounded bg-white/5 p-3 text-sm font-semibold text-white">
                Custo estimado de 1 cabaz {{ $label }}: {{ number_format($custoPorTipo[$tipo] ?? 0, 2, ',', ' ') }} €
            </div>

            <form method="post" action="{{ route('lista-cabazes.itens.store', $listaCabaz) }}" class="mt-5 grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]">
                @csrf
                <input type="hidden" name="cabaz_tipo" value="{{ $tipo }}">
                <input name="produto" placeholder="Produto" required class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <input name="categoria" placeholder="Categoria" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <input name="quantidade" type="number" min="0.001" step="0.001" placeholder="Qtd." required class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <input name="unidade" value="un" required class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <input name="preco_unitario" type="number" min="0" step="0.0001" placeholder="Preco manual" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <button class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white">Adicionar</button>
            </form>
        </section>
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = Array.from(document.querySelectorAll('[data-lista-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-lista-panel]'));
            const activate = (tipo) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.listaTab === tipo;
                    tab.classList.toggle('bg-[#3B82F6]', active);
                    tab.classList.toggle('text-white', active);
                    tab.classList.toggle('bg-white/10', !active);
                    tab.classList.toggle('text-slate-300', !active);
                });
                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.listaPanel !== tipo));
            };
            tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.listaTab)));
            activate(tabs[0]?.dataset.listaTab || 'mini');
        });
    </script>
</x-layouts.app>
