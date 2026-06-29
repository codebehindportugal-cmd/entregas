<x-layouts.app title="Produtos">
    <x-page-title title="Produtos" subtitle="Produtos do site WooCommerce, disponibilidade e margens">
        <form method="post" action="{{ route('produtos.sync') }}">
            @csrf
            <input type="hidden" name="sync_page" value="{{ $syncPage }}">
            <button class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Sincronizar site{{ $syncPage > 1 ? ' - pagina '.$syncPage : '' }}</button>
        </form>
    </x-page-title>

    <form method="get" class="mb-6 grid gap-3 rounded border border-emerald-900/10 bg-white p-4 shadow-sm md:grid-cols-[2fr_1fr_auto]">
        <input name="q" value="{{ $q }}" placeholder="Pesquisar produto, SKU ou ID..." class="rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
        <select name="estado" class="rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            <option value="" @selected($estado === '')>Todos</option>
            <option value="ativo" @selected($estado === 'ativo')>Compra ativa</option>
            <option value="inativo" @selected($estado === 'inativo')>Compra inativa</option>
            <option value="sem_fornecedor" @selected($estado === 'sem_fornecedor')>Sem fornecedor</option>
        </select>
        <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[1280px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="p-3">Produto site</th>
                    <th class="p-3">Preco site</th>
                    <th class="p-3">Epoca</th>
                    <th class="p-3">Estado compra</th>
                    <th class="p-3">Fornecedor / custo</th>
                    <th class="p-3">Margem</th>
                    <th class="p-3 text-right">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produtos as $produto)
                    <tr class="border-t border-slate-100 align-top">
                        <form method="post" action="{{ route('produtos.update', $produto) }}">
                            @csrf
                            @method('put')
                            <td class="p-3">
                                <div class="flex gap-3">
                                    @if($produto->image_url)
                                        <img src="{{ $produto->image_url }}" alt="{{ $produto->name }}" class="h-14 w-14 rounded border border-slate-200 object-cover">
                                    @endif
                                    <div class="min-w-0">
                                        <input name="name" value="{{ $produto->name }}" class="w-full rounded border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-950 shadow-sm">
                                        <p class="mt-1 text-xs text-slate-500">#{{ $produto->woo_id }} {{ $produto->sku ? ' / SKU '.$produto->sku : '' }} {{ $produto->type ? ' / '.$produto->type : '' }}</p>
                                        @if($produto->permalink)
                                            <a href="{{ $produto->permalink }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-semibold text-[#3B82F6]">Abrir no site</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <label class="block text-xs font-medium text-slate-500">Regular
                                    <input name="regular_price" type="number" min="0" step="0.01" value="{{ $produto->regular_price }}" class="mt-1 w-28 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                </label>
                                <label class="mt-2 block text-xs font-medium text-slate-500">Promocao
                                    <input name="sale_price" type="number" min="0" step="0.01" value="{{ $produto->sale_price }}" class="mt-1 w-28 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                </label>
                            </td>
                            <td class="p-3">
                                <select name="epoca" class="w-36 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                    <option value="">Sem epoca</option>
                                    @foreach($epocas as $epoca)
                                        <option value="{{ $epoca }}" @selected($produto->epoca === $epoca)>{{ $epoca }}</option>
                                    @endforeach
                                </select>
                                <label class="mt-2 flex items-center gap-2 text-xs font-medium text-slate-600">
                                    <input type="hidden" name="em_epoca" value="0">
                                    <input name="em_epoca" value="1" type="checkbox" @checked($produto->em_epoca) class="rounded border-slate-300">
                                    Em epoca
                                </label>
                            </td>
                            <td class="p-3">
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                    <input type="hidden" name="disponivel_compra" value="0">
                                    <input name="disponivel_compra" value="1" type="checkbox" @checked($produto->disponivel_compra) class="rounded border-slate-300">
                                    Disponivel
                                </label>
                                <select name="status" class="mt-2 w-28 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                    @foreach(['publish' => 'Publicado', 'draft' => 'Rascunho', 'pending' => 'Pendente', 'private' => 'Privado'] as $status => $label)
                                        <option value="{{ $status }}" @selected(($produto->status ?: 'publish') === $status)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="mt-2 inline-block rounded px-2 py-1 text-xs font-semibold {{ $produto->compraAtiva() ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">
                                    {{ $produto->compraAtiva() ? 'Compra ativa' : 'Compra inativa' }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ $produto->stock_status ?: 'sem stock status' }}{{ $produto->purchasable ? ' / compravel' : ' / nao compravel' }}</p>
                            </td>
                            <td class="p-3">
                                <select name="tabela_preco_item_id" class="w-72 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                    <option value="">Sem fornecedor associado</option>
                                    @foreach($itensFornecedor as $itemFornecedor)
                                        <option value="{{ $itemFornecedor->id }}" @selected($produto->tabela_preco_item_id === $itemFornecedor->id)>
                                            {{ $itemFornecedor->produto }} - {{ number_format((float) $itemFornecedor->preco_kg, 2, ',', ' ') }} EUR/{{ $itemFornecedor->unidade ?: 'kg' }} - {{ $itemFornecedor->tabelaPreco?->fornecedor }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="mt-2 flex gap-2">
                                    <input name="custo_quantidade" type="number" min="0" step="0.0001" value="{{ $produto->custo_quantidade }}" class="w-24 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                    <input name="custo_unidade" value="{{ $produto->custo_unidade }}" class="w-20 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Custo: {{ $produto->custoCompra() !== null ? number_format($produto->custoCompra(), 2, ',', ' ').' EUR' : 'sem fornecedor' }}</p>
                            </td>
                            <td class="p-3">
                                @php($margem = $produto->margem())
                                <p class="font-semibold {{ ($margem ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $margem !== null ? number_format($margem, 2, ',', ' ').' EUR' : '-' }}</p>
                                <p class="text-xs text-slate-500">{{ $produto->margemPercentagem() !== null ? number_format($produto->margemPercentagem(), 1, ',', ' ').'%' : '' }}</p>
                            </td>
                            <td class="p-3 text-right">
                                <button class="rounded bg-[#22C55E]/15 px-3 py-2 text-xs font-semibold text-emerald-800">Guardar</button>
                                <button name="sync_site" value="1" class="rounded bg-[#3B82F6]/15 px-3 py-2 text-xs font-semibold text-blue-800">Guardar + site</button>
                        </form>
                                <form method="post" action="{{ route('produtos.update-site', $produto) }}" class="mt-2 inline-block">
                                    @csrf
                                    <button class="rounded bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Atualizar site</button>
                                </form>
                            </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500">Ainda nao existem produtos sincronizados do site.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        {{ $produtos->links() }}
    </div>
</x-layouts.app>
