<x-layouts.app title="Gerar Lista Semanal">
    <x-page-title title="Gerar Lista Semanal" subtitle="Gera automaticamente a composição dos cabazes com produtos da época">
    </x-page-title>

    {{-- Seletor de mês --}}
    <form method="get" action="{{ route('lista-cabazes.gerar') }}"
          class="mb-6 flex flex-wrap items-end gap-3 rounded border border-emerald-900/10 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Mês</label>
            <select name="mes" onchange="this.form.submit()" class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                @foreach($meses as $num => $nome)
                    <option value="{{ $num }}" @selected($mes == $num)>{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Ano</label>
            <select name="ano" onchange="this.form.submit()" class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                @foreach($anos as $a)
                    <option value="{{ $a }}" @selected($ano == $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button class="rounded bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">Atualizar</button></noscript>
    </form>

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        {{-- Formulário de geração --}}
        <form method="post" action="{{ route('lista-cabazes.gerar.store') }}" id="form-gerar">
            @csrf

            {{-- Semana / Mês / Ano --}}
            <div class="mb-5 flex flex-wrap gap-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Semana nº</label>
                    <select name="semana_numero" class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                        @foreach($semanas as $s)
                            <option value="{{ $s }}">Semana {{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="ano" value="{{ $ano }}">
                <div class="flex items-end">
                    <p class="text-sm text-slate-600">
                        de <strong>{{ $meses[$mes] }}</strong> {{ $ano }}
                    </p>
                </div>
            </div>

            {{-- Itens por tipo de cabaz --}}
            @foreach($tipos as $tipo => $label)
                @php
                    $itensTipo = $sugestao[$tipo] ?? [];
                    $contagem = ($contagens[$tipo]['subscritores'] ?? 0) + ($contagens[$tipo]['empresas'] ?? 0);
                @endphp
                <div class="mb-5 rounded border border-slate-200 bg-white shadow-sm" id="section-{{ $tipo }}">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div>
                            <h3 class="font-bold text-slate-800">Cabaz {{ $label }}</h3>
                            <p class="text-xs text-slate-500">
                                {{ $contagens[$tipo]['subscritores'] ?? 0 }} subscrições B2C
                                + {{ $contagens[$tipo]['empresas'] ?? 0 }} empresas
                                = <strong>{{ $contagem }}</strong> cabazes
                            </p>
                        </div>
                        <button type="button" onclick="adicionarLinha('{{ $tipo }}')"
                                class="rounded bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                            + Linha
                        </button>
                    </div>
                    <table class="w-full text-sm" id="table-{{ $tipo }}">
                        <thead class="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th class="p-2 text-left">Produto</th>
                                <th class="p-2 text-left">Categoria</th>
                                <th class="p-2 text-center w-20">Qtd</th>
                                <th class="p-2 text-center w-20">Unidade</th>
                                <th class="p-2 text-center w-24">Peso kg/un</th>
                                <th class="p-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-{{ $tipo }}">
                            @forelse($itensTipo as $idx => $item)
                                @include('lista-cabazes._gerar_row', ['tipo' => $tipo, 'idx' => $idx, 'item' => $item])
                            @empty
                                <tr id="empty-{{ $tipo }}">
                                    <td colspan="6" class="p-3 text-center text-xs text-slate-400">
                                        Sem produtos da época para este cabaz.
                                        @if(empty($sugestao) || count($sugestao[$tipo] ?? []) === 0)
                                            <a href="{{ route('sazonalidade.index') }}" class="text-emerald-600 underline">Configurar sazonalidade</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach

            <div class="flex justify-end gap-3">
                <a href="{{ route('lista-cabazes.index') }}" class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancelar
                </a>
                <button type="submit" class="rounded bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Criar lista semanal
                </button>
            </div>
        </form>

        {{-- Painel lateral: produtos da época --}}
        <div class="space-y-4">
            <div class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-bold text-slate-800">Época: {{ $meses[$mes] }} {{ $ano }}</h3>
                @if($produtosEpoca->isEmpty())
                    <p class="text-sm text-slate-400">Nenhum produto configurado para este mês.</p>
                    <a href="{{ route('sazonalidade.index') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-600 underline">
                        Configurar sazonalidade
                    </a>
                @else
                    @foreach($produtosEpoca as $categoria => $itens)
                        <div class="mb-3">
                            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ ucfirst($categoria) }}</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($itens as $item)
                                    <span class="cursor-pointer rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800
                                                 hover:bg-emerald-100"
                                          onclick="adicionarProduto('{{ addslashes($item->produto) }}', '{{ addslashes($item->categoria) }}')"
                                          title="Clica para adicionar">
                                        {{ $item->produto }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p class="mt-2 text-[10px] text-slate-400">Clica num produto para o adicionar a um cabaz.</p>
                @endif
            </div>

            <div class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-2 font-bold text-slate-800">Encomendas ativas</h3>
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500">
                        <tr>
                            <th class="pb-1 text-left">Cabaz</th>
                            <th class="pb-1 text-center">B2C</th>
                            <th class="pb-1 text-center">B2B</th>
                            <th class="pb-1 text-center font-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tipos as $tipo => $label)
                            @php $total = ($contagens[$tipo]['subscritores'] ?? 0) + ($contagens[$tipo]['empresas'] ?? 0); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="py-1 font-semibold">{{ $label }}</td>
                                <td class="py-1 text-center text-slate-600">{{ $contagens[$tipo]['subscritores'] ?? 0 }}</td>
                                <td class="py-1 text-center text-slate-600">{{ $contagens[$tipo]['empresas'] ?? 0 }}</td>
                                <td class="py-1 text-center font-bold {{ $total > 0 ? 'text-emerald-700' : 'text-slate-300' }}">{{ $total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    const contadores = {
        @foreach($tipos as $tipo => $label)
            '{{ $tipo }}': {{ count($sugestao[$tipo] ?? []) }},
        @endforeach
    };

    function adicionarLinha(tipo, produto = '', categoria = '') {
        const idx = contadores[tipo]++;
        const tbody = document.getElementById('tbody-' + tipo);
        const empty = document.getElementById('empty-' + tipo);
        if (empty) empty.remove();

        const tr = document.createElement('tr');
        tr.className = 'border-t border-slate-100';
        tr.innerHTML = rowHtml(tipo, idx, produto, categoria);
        tbody.appendChild(tr);
    }

    function adicionarProduto(produto, categoria) {
        const tipo = prompt('Em que cabaz?\n\nmini, pequeno, medio, grande', 'medio');
        if (!tipo || !['mini','pequeno','medio','grande'].includes(tipo)) return;
        adicionarLinha(tipo, produto, categoria);
        document.getElementById('section-' + tipo).scrollIntoView({behavior: 'smooth'});
    }

    function removerLinha(btn) {
        btn.closest('tr').remove();
    }

    function rowHtml(tipo, idx, produto, categoria) {
        const esc = s => s.replace(/"/g, '&quot;').replace(/</g, '&lt;');
        return `
            <td class="p-2">
                <input name="itens[${idx}][produto]" value="${esc(produto)}" required placeholder="Nome do produto"
                       class="w-full min-w-[140px] rounded border border-slate-200 px-2 py-1 text-xs text-slate-950">
                <input type="hidden" name="itens[${idx}][cabaz_tipo]" value="${tipo}">
            </td>
            <td class="p-2">
                <input name="itens[${idx}][categoria]" value="${esc(categoria)}" placeholder="fruta, legume..."
                       class="w-full min-w-[80px] rounded border border-slate-200 px-2 py-1 text-xs text-slate-950">
            </td>
            <td class="p-2">
                <input name="itens[${idx}][quantidade]" type="number" step="0.001" min="0.001" value="1" required
                       class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950">
            </td>
            <td class="p-2">
                <input name="itens[${idx}][unidade]" value="un"
                       class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950">
            </td>
            <td class="p-2">
                <input name="itens[${idx}][peso_unitario_kg]" type="number" step="0.001" min="0"
                       class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950" placeholder="—">
            </td>
            <td class="p-2 text-center">
                <button type="button" onclick="removerLinha(this)" class="text-rose-400 hover:text-rose-600 font-bold text-base leading-none">×</button>
            </td>
        `;
    }
    </script>
</x-layouts.app>
