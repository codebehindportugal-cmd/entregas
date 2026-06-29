<x-layouts.app title="Produtos Comprados">
    @php
        $syncFields = collect(request()->input('sync_fields', []))
            ->map(fn ($field) => (string) $field)
            ->all();
        if ($syncFields === []) {
            $syncFields = ['identity', 'prices', 'images', 'description', 'short_description', 'availability', 'metadata'];
        }
        $syncOptions = [
            'identity' => 'Produto',
            'prices' => 'Precos',
            'images' => 'Imagens',
            'description' => 'Descricao',
            'short_description' => 'Breve descricao',
            'availability' => 'Disponibilidade',
            'metadata' => 'Epoca/metadados',
        ];
    @endphp

    <x-page-title title="Produtos Comprados" subtitle="Histórico de compras agrupado por produto, com custos médios e fornecedores">
    </x-page-title>

    <form method="post" action="{{ route('produtos.sync') }}" class="mb-6 rounded border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <input type="hidden" name="sync_page" value="{{ max(1, (int) request('sync_page', 1)) }}">
        <div class="flex flex-wrap items-center gap-3">
            @foreach($syncOptions as $value => $label)
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="sync_fields[]" value="{{ $value }}" @checked(in_array($value, $syncFields, true)) class="rounded border-slate-300">
                    {{ $label }}
                </label>
            @endforeach
            <button class="ml-auto rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Sincronizar produtos</button>
        </div>
    </form>

    <form method="get" class="mb-6 grid gap-3 rounded border border-emerald-900/10 bg-white p-4 shadow-sm md:grid-cols-[2fr_1fr_auto]">
        <input name="q" value="{{ $q }}" placeholder="Pesquisar produto..." class="rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
        <select name="fornecedor" class="rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            <option value="">Todos os fornecedores</option>
            @foreach($fornecedores as $f)
                <option value="{{ $f }}" @selected($fornecedor === $f)>{{ $f }}</option>
            @endforeach
        </select>
        <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="p-3">Produto</th>
                    <th class="p-3">Unidade</th>
                    <th class="p-3 text-right">Preço médio</th>
                    <th class="p-3 text-right">Mín / Máx</th>
                    <th class="p-3 text-right">Qtd total</th>
                    <th class="p-3">Última compra</th>
                    <th class="p-3">Fornecedores</th>
                    <th class="p-3 text-right">Entradas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produtos as $produto)
                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                        <td class="p-3 font-semibold text-slate-900">{{ $produto->descricao }}</td>
                        <td class="p-3 text-slate-600">{{ $produto->unidade_compra ?: '—' }}</td>
                        <td class="p-3 text-right font-semibold text-slate-900">
                            {{ number_format((float) $produto->preco_medio, 4, ',', ' ') }} €
                        </td>
                        <td class="p-3 text-right text-xs text-slate-500">
                            {{ number_format((float) $produto->preco_min, 4, ',', ' ') }} €
                            <span class="mx-0.5 text-slate-300">/</span>
                            {{ number_format((float) $produto->preco_max, 4, ',', ' ') }} €
                        </td>
                        <td class="p-3 text-right text-slate-700">
                            {{ number_format((float) $produto->quantidade_total, 3, ',', ' ') }}
                            {{ $produto->unidade_compra }}
                        </td>
                        <td class="p-3 text-slate-600">
                            {{ $produto->ultima_compra ? \Carbon\Carbon::parse($produto->ultima_compra)->format('d/m/Y') : '—' }}
                        </td>
                        <td class="p-3 text-xs text-slate-500">{{ $produto->fornecedores ?: '—' }}</td>
                        <td class="p-3 text-right text-slate-600">{{ $produto->total_linhas }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-6 text-center text-slate-500">Ainda não existem produtos comprados registados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        {{ $produtos->links() }}
    </div>
</x-layouts.app>
