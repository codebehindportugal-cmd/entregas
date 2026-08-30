<x-layouts.app title="{{ $lista->tituloFormatado() }}">
    <x-page-title title="{{ $lista->tituloFormatado() }}" subtitle="Produtos e quantidades de cada tamanho de cabaz">
        <a href="{{ route('lista-cabazes.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
    </x-page-title>

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($tipos as $tipo => $label)
            <div class="rounded border border-emerald-900/10 bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-[#14532d]">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $resumo[$tipo]['linhas'] }} <span class="text-sm font-normal text-slate-500">produtos</span></p>
                <p class="mt-1 text-xs text-slate-500">
                    Custo: {{ number_format($resumo[$tipo]['custo'], 2, ',', ' ') }} EUR
                    @if($resumo[$tipo]['sem_preco'] > 0)
                        <span class="text-amber-700">({{ $resumo[$tipo]['sem_preco'] }} sem preco)</span>
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('lista-cabazes.update', $lista) }}">
        @csrf
        @method('put')

        <div class="mb-6 grid gap-3 rounded border border-emerald-900/10 bg-white p-5 shadow-sm lg:grid-cols-[2fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700">Descricao
                <input name="descricao" type="text" value="{{ old('descricao', $lista->descricao) }}" placeholder="Ex.: Semana de 1 a 7 de setembro"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Estado
                <select name="estado" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                    <option value="rascunho" @selected($lista->estado === 'rascunho')>Rascunho</option>
                    <option value="publicada" @selected($lista->estado === 'publicada')>Publicada</option>
                </select>
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar lista</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-1 rounded border border-emerald-900/10 bg-white p-1 shadow-sm" data-cabaz-tabs>
            @foreach($tipos as $tipo => $label)
                <button type="button" data-cabaz-tab="{{ $tipo }}" class="rounded px-4 py-2 text-sm font-semibold text-slate-600">{{ $label }}</button>
            @endforeach
        </div>

        @php $indice = 0; @endphp

        @foreach($tipos as $tipo => $label)
            <div data-cabaz-panel="{{ $tipo }}" class="hidden">
                <div class="overflow-x-auto rounded border border-emerald-900/10 bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-emerald-50 text-slate-700">
                            <tr>
                                <th class="p-3">Produto</th>
                                <th class="p-3">Categoria</th>
                                <th class="p-3 w-28">Quantidade</th>
                                <th class="p-3 w-24">Unidade</th>
                                <th class="p-3 w-28">Peso un. (kg)</th>
                                <th class="p-3 w-28" title="Por quilo quando a linha tem peso ou unidade de peso; por unidade nos produtos a unidade sem peso.">Preco</th>
                                <th class="p-3 w-16">Remover</th>
                            </tr>
                        </thead>
                        <tbody data-cabaz-linhas="{{ $tipo }}">
                            @foreach($itensPorTipo[$tipo] as $item)
                                <tr class="border-t border-slate-100">
                                    <td class="p-2">
                                        <input type="hidden" name="itens[{{ $indice }}][id]" value="{{ $item->id }}">
                                        <input type="hidden" name="itens[{{ $indice }}][cabaz_tipo]" value="{{ $tipo }}">
                                        <input name="itens[{{ $indice }}][produto]" value="{{ $item->produto }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950">
                                    </td>
                                    <td class="p-2"><input name="itens[{{ $indice }}][categoria]" value="{{ $item->categoria }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>
                                    <td class="p-2"><input name="itens[{{ $indice }}][quantidade]" type="number" step="0.001" min="0" value="{{ (float) $item->quantidade }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>
                                    <td class="p-2"><input name="itens[{{ $indice }}][unidade]" value="{{ $item->unidade }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>
                                    <td class="p-2"><input name="itens[{{ $indice }}][peso_unitario_kg]" type="number" step="0.0001" min="0" value="{{ $item->peso_unitario_kg !== null ? (float) $item->peso_unitario_kg : '' }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>
                                    <td class="p-2"><input name="itens[{{ $indice }}][preco_unitario]" type="number" step="0.0001" min="0" value="{{ $item->preco_unitario !== null ? (float) $item->preco_unitario : '' }}" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>
                                    <td class="p-2 text-center"><input name="itens[{{ $indice }}][remover]" type="checkbox" value="1" class="rounded border-slate-300"></td>
                                </tr>
                                @php $indice++; @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" data-cabaz-adicionar="{{ $tipo }}" class="mt-3 rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    + Adicionar produto ao {{ $label }}
                </button>
            </div>
        @endforeach

        <input type="hidden" id="proximoIndice" value="{{ $indice }}">

        <div class="mt-6">
            <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar lista</button>
            <span class="ml-3 text-xs text-slate-500">Linhas sem nome de produto sao ignoradas.</span>
        </div>
    </form>

    <form method="post" action="{{ route('lista-cabazes.destroy', $lista) }}" class="mt-8" onsubmit="return confirm('Remover esta lista e todos os produtos dela?');">
        @csrf
        @method('delete')
        <button class="rounded border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Remover lista</button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = Array.from(document.querySelectorAll('[data-cabaz-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-cabaz-panel]'));
            const proximo = document.getElementById('proximoIndice');

            const abrir = (tipo) => {
                tabs.forEach((tab) => {
                    const ativo = tab.dataset.cabazTab === tipo;
                    tab.className = ativo
                        ? 'rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]'
                        : 'rounded px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50';
                });
                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.cabazPanel !== tipo));

                try { localStorage.setItem('listaCabazTipo', tipo); } catch (e) { /* ignora */ }
            };

            tabs.forEach((tab) => tab.addEventListener('click', () => abrir(tab.dataset.cabazTab)));

            let inicial = 'mini';
            try { inicial = localStorage.getItem('listaCabazTipo') || 'mini'; } catch (e) { /* ignora */ }
            abrir(panels.some((p) => p.dataset.cabazPanel === inicial) ? inicial : 'mini');

            const campo = (indice, tipo, nome, atributos = '') =>
                `<td class="p-2"><input name="itens[${indice}][${nome}]" ${atributos} class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950"></td>`;

            document.querySelectorAll('[data-cabaz-adicionar]').forEach((botao) => {
                botao.addEventListener('click', () => {
                    const tipo = botao.dataset.cabazAdicionar;
                    const corpo = document.querySelector(`[data-cabaz-linhas="${tipo}"]`);
                    const indice = parseInt(proximo.value, 10);
                    const linha = document.createElement('tr');

                    linha.className = 'border-t border-slate-100';
                    linha.innerHTML =
                        `<td class="p-2">`
                        + `<input type="hidden" name="itens[${indice}][cabaz_tipo]" value="${tipo}">`
                        + `<input name="itens[${indice}][produto]" class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950" autofocus></td>`
                        + campo(indice, tipo, 'categoria')
                        + campo(indice, tipo, 'quantidade', 'type="number" step="0.001" min="0" value="1"')
                        + campo(indice, tipo, 'unidade', 'value="un"')
                        + campo(indice, tipo, 'peso_unitario_kg', 'type="number" step="0.0001" min="0"')
                        + campo(indice, tipo, 'preco_unitario', 'type="number" step="0.0001" min="0"')
                        + `<td class="p-2 text-center"><input name="itens[${indice}][remover]" type="checkbox" value="1" class="rounded border-slate-300"></td>`;

                    corpo.appendChild(linha);
                    proximo.value = indice + 1;
                    linha.querySelector('input[name$="[produto]"]').focus();
                });
            });
        });
    </script>
</x-layouts.app>
