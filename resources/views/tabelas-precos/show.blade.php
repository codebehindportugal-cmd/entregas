<x-layouts.app title="Tabela de precos">
    <x-page-title title="{{ $tabelaPreco->tituloFormatado() }}">
        <form method="post" action="{{ route('tabelas-precos.clonar', $tabelaPreco) }}">
            @csrf
            <button class="rounded bg-[#F59E0B]/20 px-4 py-2 text-sm font-semibold text-amber-200">Clonar esta tabela</button>
        </form>
    </x-page-title>

    <form method="get" class="mb-5 rounded border border-white/10 bg-[#151E2D] p-4">
        <input name="q" value="{{ $q }}" placeholder="Pesquisar produto..." class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </form>

    @foreach($categorias as $categoria)
        <section class="mb-6 overflow-hidden rounded border border-white/10 bg-[#151E2D]">
            <div class="bg-white/5 p-4">
                <h2 class="font-semibold text-white">{{ $categoria }}</h2>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="text-slate-400">
                    <tr>
                        <th class="p-3">Produto</th>
                        <th class="p-3">Origem</th>
                        <th class="p-3">Calibre</th>
                        <th class="p-3">Preco</th>
                        <th class="p-3">C/IVA</th>
                        <th class="p-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($itensPorCategoria[$categoria] ?? collect()) as $item)
                        <tr class="border-t border-white/10">
                            <form method="post" action="{{ route('tabelas-precos.itens.update', $item) }}">
                                @csrf
                                @method('put')
                                <input type="hidden" name="categoria" value="{{ $categoria }}">
                                <td class="p-2"><input name="produto" value="{{ $item->produto }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                <td class="p-2"><input name="origem" value="{{ $item->origem }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                <td class="p-2"><input name="calibre" value="{{ $item->calibre }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                <td class="p-2"><input name="preco_kg" type="number" step="0.0001" min="0" value="{{ $item->preco_kg }}" class="w-28 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                                <td class="p-2">
                                    <input name="preco_kg_iva" type="number" step="0.0001" min="0" value="{{ $item->preco_kg_iva }}" class="w-28 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                    <input type="hidden" name="unidade" value="{{ $item->unidade }}">
                                </td>
                                <td class="p-2 text-right">
                                    <button class="rounded bg-[#22C55E]/20 px-3 py-2 text-xs font-semibold text-emerald-200">Guardar</button>
                            </form>
                                    <form method="post" action="{{ route('tabelas-precos.itens.destroy', $item) }}" class="inline-block" onsubmit="return confirm('Remover este produto?');">
                                        @csrf
                                        @method('delete')
                                        <button class="rounded bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Apagar</button>
                                    </form>
                                </td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-white/10 bg-white/5">
                        <form method="post" action="{{ route('tabelas-precos.itens.store', $tabelaPreco) }}">
                            @csrf
                            <input type="hidden" name="categoria" value="{{ $categoria }}">
                            <td class="p-2"><input name="produto" placeholder="Produto" required class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                            <td class="p-2"><input name="origem" placeholder="Origem" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                            <td class="p-2"><input name="calibre" placeholder="Calibre" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                            <td class="p-2"><input name="preco_kg" type="number" step="0.0001" min="0" required placeholder="Preco" class="w-28 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white"></td>
                            <td class="p-2">
                                <input name="preco_kg_iva" type="number" step="0.0001" min="0" required placeholder="IVA" class="w-28 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                <input type="hidden" name="unidade" value="kg">
                            </td>
                            <td class="p-2 text-right"><button class="rounded bg-[#3B82F6] px-3 py-2 text-xs font-semibold text-white">Adicionar</button></td>
                        </form>
                    </tr>
                </tbody>
            </table>
        </section>
    @endforeach
</x-layouts.app>
