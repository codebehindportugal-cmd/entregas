<x-layouts.app title="{{ $despesa->titulo }}">
    @php
        $pendingAiJobs = $despesa->aiJobs->where('status', 'pending');
        $latestJob = $despesa->aiJobs->first();
    @endphp

    {{-- Header --}}
    <div class="mb-6">
        <div class="mb-3">
            <a href="{{ route('despesas.index') }}" class="text-sm text-slate-400 hover:text-slate-200">← Faturas e entradas</a>
        </div>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-white">{{ $despesa->titulo }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-400">
                    @if($despesa->numero_fatura)
                        <span>N.º {{ $despesa->numero_fatura }}</span>
                        <span class="text-slate-600">·</span>
                    @endif
                    @if($despesa->fornecedor)
                        <span>{{ $despesa->fornecedor }}</span>
                        <span class="text-slate-600">·</span>
                    @endif
                    <span>{{ $despesa->data->format('d/m/Y') }}</span>
                    @if($latestJob)
                        <span class="text-slate-600">·</span>
                        @if($latestJob->status === 'pending')
                            <span class="rounded-full bg-blue-500/20 px-2 py-0.5 text-xs font-semibold text-blue-300">IA pendente</span>
                        @elseif($latestJob->status === 'done')
                            <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-300">IA concluida</span>
                        @elseif($latestJob->status === 'failed')
                            <span class="rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-300">IA falhou</span>
                        @endif
                    @endif
                </div>
                @if($despesa->notas)
                    <p class="mt-2 max-w-prose text-sm text-slate-400">{{ $despesa->notas }}</p>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <a href="{{ route('despesas.edit', $despesa) }}"
                    class="rounded border border-white/10 bg-[#151E2D] px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10">
                    Editar
                </a>
                <form method="post" action="{{ route('despesas.destroy', $despesa) }}"
                    onsubmit="return confirm('Remover esta entrada permanentemente?')">
                    @csrf @method('delete')
                    <button type="submit"
                        class="rounded border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500/20">
                        Remover
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('status'))
        <div class="mb-5 rounded border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    {{-- Banner IA a processar --}}
    @if($pendingAiJobs->isNotEmpty())
        <div class="mb-5 flex items-center gap-3 rounded border border-blue-400/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-200">
            <svg class="h-5 w-5 shrink-0 animate-spin text-blue-300" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <div>
                <strong>IA a processar {{ $pendingAiJobs->count() === 1 ? 'a imagem' : 'as imagens' }}...</strong>
                <span class="ml-1 text-blue-300">Os produtos serao adicionados automaticamente. A pagina actualiza em 5 s.</span>
            </div>
        </div>
        <script>setTimeout(function () { location.reload(); }, 5000);</script>
    @endif

    {{-- Totais --}}
    <div class="mb-6 grid grid-cols-3 gap-3">
        <div class="rounded border border-white/10 bg-[#151E2D] p-4 text-center">
            <p class="mb-1 text-xs text-slate-400">Subtotal s/ IVA</p>
            <p class="text-xl font-bold text-white">{{ number_format($despesa->subtotal_calculado, 2, ',', ' ') }} €</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4 text-center">
            <p class="mb-1 text-xs text-slate-400">IVA</p>
            <p class="text-xl font-bold text-white">{{ number_format($despesa->iva_calculado, 2, ',', ' ') }} €</p>
        </div>
        <div class="rounded border border-emerald-500/20 bg-emerald-500/10 p-4 text-center">
            <p class="mb-1 text-xs text-emerald-300">Total c/ IVA</p>
            <p class="text-xl font-bold text-emerald-400">{{ number_format($despesa->total_fatura, 2, ',', ' ') }} €</p>
        </div>
    </div>

    {{-- Fotos --}}
    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-400">Fotos da fatura</h2>
        <div id="fotos-grid" class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
            @foreach($despesa->fotos as $foto)
            <div class="foto-item group relative cursor-pointer" data-foto-id="{{ $foto->id }}"
                onclick="openLightbox('{{ $foto->url }}')">
                <img src="{{ $foto->url }}"
                    class="h-24 w-full rounded-lg border border-white/10 object-cover transition hover:opacity-80">
                <button type="button" class="btn-delete-foto absolute right-1 top-1 hidden h-6 w-6 items-center justify-center rounded-full bg-red-600/80 text-xs font-bold text-white hover:bg-red-600 group-hover:flex"
                    onclick="event.stopPropagation()">&times;</button>
            </div>
            @endforeach

            @if($despesa->ficheiro_path && $despesa->fotos->isEmpty())
            @php($legacyUrl = route('public-files.show', ['path' => $despesa->ficheiro_path]))
            <div class="group relative cursor-pointer" onclick="openLightbox('{{ $legacyUrl }}')">
                <img src="{{ $legacyUrl }}"
                    class="h-24 w-full rounded-lg border border-amber-500/30 object-cover opacity-80 transition hover:opacity-100">
                <span class="absolute bottom-0 left-0 right-0 rounded-b-lg bg-black/60 py-0.5 text-center text-xs text-amber-300">antigo</span>
            </div>
            @endif

            <button type="button" id="btn-add-foto"
                class="flex h-24 w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-white/20 bg-[#0A0F1A] text-slate-400 transition hover:border-emerald-500/40 hover:text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-xs">Adicionar</span>
            </button>
        </div>
        <input type="file" id="foto-input" accept="image/*,application/pdf" multiple class="hidden">
        <p id="upload-status" class="mt-2 hidden text-xs text-slate-400"></p>
    </div>

    {{-- Linhas de produto --}}
    <div class="rounded border border-white/10 bg-[#151E2D] p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Linhas da fatura</h2>
            <a href="{{ route('despesas.edit', $despesa) }}"
                class="rounded border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/10">
                Editar tudo
            </a>
        </div>

        <div class="overflow-x-auto">
            <table id="items-table" class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs text-slate-500">
                        <th class="pb-2 text-left font-medium">Descricao</th>
                        <th class="pb-2 text-right font-medium">Qtd compra</th>
                        <th class="pb-2 text-right font-medium">Qtd unidades</th>
                        <th class="pb-2 text-right font-medium">Custo/unid.</th>
                        <th class="pb-2 text-right font-medium">Preco unit.</th>
                        <th class="pb-2 text-right font-medium">IVA</th>
                        <th class="pb-2 text-right font-medium">Total c/ IVA</th>
                    </tr>
                </thead>
                <tbody id="items-tbody">
                    @foreach($despesa->items as $item)
                        <tr class="border-b border-white/5">
                            <td class="py-2 pr-4 text-slate-200">{{ $item->descricao }}</td>
                            <td class="py-2 text-right text-slate-400">{{ number_format((float) $item->quantidade, 3, ',', '') }} {{ $item->unidade_compra ?? 'un' }}</td>
                            <td class="py-2 text-right text-slate-400">{{ number_format((float) $item->quantidade_unidades, 3, ',', '') }} un</td>
                            <td class="py-2 text-right font-semibold text-emerald-300">
                                {{ $item->custo_unitario !== null ? number_format($item->custo_unitario, 4, ',', '').' €' : '—' }}
                            </td>
                            <td class="py-2 text-right text-slate-400">{{ number_format((float) $item->preco_unitario, 4, ',', '') }} €</td>
                            <td class="py-2 text-right text-slate-400">{{ number_format((float) $item->iva_percentagem, 0) }}%</td>
                            <td class="py-2 text-right font-semibold text-white">{{ number_format($item->total_com_iva, 2, ',', '') }} €</td>
                        </tr>
                    @endforeach
                    @if($despesa->items->isEmpty() && $pendingAiJobs->isNotEmpty())
                        <tr id="row-ia-pending">
                            <td colspan="7" class="py-3 text-sm text-slate-400">A IA esta a processar as imagens e vai preencher as linhas automaticamente...</td>
                        </tr>
                    @elseif($despesa->items->isEmpty())
                        <tr id="row-empty">
                            <td colspan="7" class="py-3 text-sm text-slate-400">Nenhuma linha. Use o formulario abaixo ou carregue um PDF.</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr id="items-tfoot" class="border-t border-white/10 {{ $despesa->items->isEmpty() ? 'hidden' : '' }}">
                        <td colspan="6" class="pt-2 text-right text-xs text-slate-500">
                            Subtotal s/ IVA: <span id="subtotal-val">{{ number_format($despesa->subtotal_calculado, 2, ',', '') }}</span> €
                            &nbsp;&nbsp; IVA: <span id="iva-val">{{ number_format($despesa->iva_calculado, 2, ',', '') }}</span> €
                        </td>
                        <td class="pt-2 text-right font-bold text-emerald-400">
                            <span id="total-val">{{ number_format($despesa->total_fatura, 2, ',', '') }}</span> €
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Formulario rapido de adicao de produto --}}
        <div class="mt-5 border-t border-white/10 pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Adicionar produto</p>
            <form id="form-add-item" autocomplete="off">
                <div class="flex flex-wrap gap-2">
                    <div class="relative min-w-0 flex-1" style="min-width:180px">
                        <input id="item-descricao" type="text" placeholder="Descricao do produto"
                            class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 placeholder-slate-600 focus:border-emerald-500/50 focus:outline-none"
                            required maxlength="255">
                        <ul id="autocomplete-list"
                            class="absolute left-0 top-full z-20 mt-0.5 hidden w-full overflow-hidden rounded border border-white/10 bg-[#0D1522] text-sm shadow-xl">
                        </ul>
                    </div>
                    <input id="item-quantidade" type="number" placeholder="Qtd" step="0.001" min="0.001"
                        class="w-24 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 placeholder-slate-600 focus:border-emerald-500/50 focus:outline-none"
                        required>
                    <select id="item-unidade"
                        class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 focus:border-emerald-500/50 focus:outline-none">
                        <option value="un">un</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="l">l</option>
                        <option value="cx">cx</option>
                        <option value="sc">sc</option>
                        <option value="frd">frd</option>
                        <option value="maco">maco</option>
                    </select>
                    <input id="item-preco" type="number" placeholder="Preco s/ IVA" step="0.0001" min="0"
                        class="w-32 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 placeholder-slate-600 focus:border-emerald-500/50 focus:outline-none"
                        required>
                    <select id="item-iva"
                        class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 focus:border-emerald-500/50 focus:outline-none">
                        <option value="6">6%</option>
                        <option value="0">0%</option>
                        <option value="13">13%</option>
                        <option value="23">23%</option>
                    </select>
                    <button type="submit" id="btn-add-item"
                        class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50">
                        + Adicionar
                    </button>
                </div>
                <p id="add-item-error" class="mt-1.5 hidden text-xs text-red-400"></p>
            </form>
        </div>
    </div>

    {{-- Lightbox + AJAX JS --}}
    <script>
    (function () {
        var csrfToken = '{{ csrf_token() }}';
        var despesaId = {{ $despesa->id }};
        var fornecedor = '{{ addslashes($despesa->fornecedor ?? '') }}';

        // ── Utilitários ──────────────────────────────────────────────────────────

        function fmt(n, dec) {
            return Number(n).toLocaleString('pt-PT', { minimumFractionDigits: dec, maximumFractionDigits: dec });
        }

        function showToast(msg, type) {
            var toast = document.createElement('div');
            toast.className = 'fixed right-4 top-4 z-[9999] max-w-xs rounded-lg border px-4 py-3 text-sm font-medium shadow-xl transition-opacity duration-300 '
                + (type === 'success' ? 'border-emerald-400/50 bg-emerald-600 text-white' : 'border-amber-400/50 bg-amber-500 text-white');
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(function () {
                toast.style.opacity = '0';
                setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
            }, 3800);
        }

        // ── Lightbox ─────────────────────────────────────────────────────────────

        function openLightbox(url) {
            var lb = document.createElement('div');
            lb.className = 'fixed inset-0 z-50 flex cursor-pointer items-center justify-center bg-black/90 p-4';
            var img = document.createElement('img');
            img.src = url;
            img.className = 'max-h-full max-w-full rounded object-contain';
            lb.appendChild(img);
            lb.addEventListener('click', function () { lb.remove(); });
            document.body.appendChild(lb);
        }
        window.openLightbox = openLightbox;

        // ── Fotos ────────────────────────────────────────────────────────────────

        function deleteFoto(fotoId, itemEl) {
            if (!confirm('Remover esta foto?')) return;
            fetch('/despesas/fotos/' + fotoId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }).then(function (r) {
                if (r.ok) { itemEl.remove(); showToast('Foto removida.', 'success'); }
                else showToast('Erro ao remover foto.', 'warn');
            }).catch(function () { showToast('Erro ao remover foto.', 'warn'); });
        }

        document.querySelectorAll('.btn-delete-foto').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var item = btn.closest('.foto-item');
                if (item) deleteFoto(item.dataset.fotoId, item);
            });
        });

        function addFotoAoGrid(id, url, isPdf) {
            var grid = document.getElementById('fotos-grid');
            var addBtn = document.getElementById('btn-add-foto');

            var div = document.createElement('div');
            div.className = 'foto-item group relative cursor-pointer';
            div.dataset.fotoId = id;

            var inner;
            if (isPdf) {
                // Mostrar ícone PDF em vez de imagem
                inner = document.createElement('a');
                inner.href = url;
                inner.target = '_blank';
                inner.rel = 'noopener';
                inner.className = 'flex h-24 w-full flex-col items-center justify-center gap-1 rounded-lg border border-blue-500/30 bg-blue-500/10 text-blue-300 transition hover:bg-blue-500/20';
                inner.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg><span class="text-xs font-semibold">PDF</span>';
            } else {
                inner = document.createElement('img');
                inner.src = url;
                inner.className = 'h-24 w-full rounded-lg border border-emerald-500/30 object-cover transition hover:opacity-80';
                div.addEventListener('click', function () { openLightbox(url); });
            }

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-delete-foto absolute right-1 top-1 hidden h-6 w-6 items-center justify-center rounded-full bg-red-600/80 text-xs font-bold text-white hover:bg-red-600 group-hover:flex';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                deleteFoto(id, div);
            });

            div.appendChild(inner);
            div.appendChild(btn);
            grid.insertBefore(div, addBtn);
        }

        var btnAdd = document.getElementById('btn-add-foto');
        var fotoInput = document.getElementById('foto-input');
        var uploadStatus = document.getElementById('upload-status');

        if (btnAdd) btnAdd.addEventListener('click', function () { fotoInput.click(); });

        if (fotoInput) {
            fotoInput.addEventListener('change', function () {
                if (!this.files || !this.files.length) return;
                var files = Array.from(this.files);
                this.value = '';

                var hasPdf = files.some(function (f) { return f.type === 'application/pdf'; });
                uploadStatus.textContent = hasPdf ? 'A processar PDF...' : 'A enviar ' + files.length + ' foto(s)...';
                uploadStatus.classList.remove('hidden');
                if (btnAdd) { btnAdd.disabled = true; btnAdd.style.opacity = '0.6'; }

                var totalItemsAdded = 0;

                Promise.all(files.map(function (file) {
                    var fd = new FormData();
                    fd.append('foto', file);
                    return fetch('/despesas/' + despesaId + '/fotos', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: fd
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.id) addFotoAoGrid(data.id, data.url, data.pdf);
                        if (data.items_added) totalItemsAdded += data.items_added;
                    });
                })).then(function () {
                    if (totalItemsAdded > 0) {
                        uploadStatus.textContent = 'PDF processado: ' + totalItemsAdded + ' produto(s) importado(s). A recarregar...';
                        showToast('PDF: ' + totalItemsAdded + ' produto(s) importado(s)!', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else if (hasPdf) {
                        uploadStatus.textContent = 'PDF carregado. Nenhum produto identificado automaticamente.';
                        showToast('PDF carregado. Nenhum produto identificado.', 'warn');
                        setTimeout(function () { uploadStatus.classList.add('hidden'); }, 4000);
                    } else {
                        uploadStatus.textContent = files.length + ' foto(s) adicionada(s).';
                        setTimeout(function () { uploadStatus.classList.add('hidden'); }, 3000);
                        showToast(files.length === 1 ? 'Foto adicionada!' : files.length + ' fotos adicionadas!', 'success');
                    }
                }).catch(function () {
                    uploadStatus.textContent = 'Erro ao enviar.';
                    showToast('Erro ao enviar.', 'warn');
                }).finally(function () {
                    if (btnAdd) { btnAdd.disabled = false; btnAdd.style.opacity = ''; }
                });
            });
        }

        // ── Tabela de itens — actualizar DOM ─────────────────────────────────────

        function addItemRow(item) {
            var tbody = document.getElementById('items-tbody');

            // Remover linha "vazia"
            var rowEmpty = document.getElementById('row-empty');
            if (rowEmpty) rowEmpty.remove();
            var rowIa = document.getElementById('row-ia-pending');
            if (rowIa) rowIa.remove();

            var tr = document.createElement('tr');
            tr.className = 'border-b border-white/5 bg-emerald-500/5';

            var custo = item.custo_unitario !== null
                ? fmt(item.custo_unitario, 4) + ' €'
                : '—';

            tr.innerHTML = '<td class="py-2 pr-4 text-slate-200">' + escHtml(item.descricao) + '</td>'
                + '<td class="py-2 text-right text-slate-400">' + fmt(item.quantidade, 3) + ' ' + escHtml(item.unidade_compra) + '</td>'
                + '<td class="py-2 text-right text-slate-400">' + fmt(item.quantidade_unidades, 3) + ' un</td>'
                + '<td class="py-2 text-right font-semibold text-emerald-300">' + custo + '</td>'
                + '<td class="py-2 text-right text-slate-400">' + fmt(item.preco_unitario, 4) + ' €</td>'
                + '<td class="py-2 text-right text-slate-400">' + fmt(item.iva_percentagem, 0) + '%</td>'
                + '<td class="py-2 text-right font-semibold text-white">' + fmt(item.total_com_iva, 2) + ' €</td>';

            tbody.appendChild(tr);

            // Mostrar rodapé se estava escondido
            document.getElementById('items-tfoot').classList.remove('hidden');
        }

        function updateTotais(totais) {
            document.getElementById('subtotal-val').textContent = fmt(totais.subtotal, 2);
            document.getElementById('iva-val').textContent = fmt(totais.iva, 2);
            document.getElementById('total-val').textContent = fmt(totais.total, 2);

            // Actualizar também os cards no topo
            var cards = document.querySelectorAll('[data-total-card]');
            cards.forEach(function (c) {
                if (c.dataset.totalCard === 'subtotal') c.textContent = fmt(totais.subtotal, 2) + ' €';
                if (c.dataset.totalCard === 'iva')      c.textContent = fmt(totais.iva, 2) + ' €';
                if (c.dataset.totalCard === 'total')    c.textContent = fmt(totais.total, 2) + ' €';
            });
        }

        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Autocomplete ─────────────────────────────────────────────────────────

        var acList = document.getElementById('autocomplete-list');
        var acCache = {};
        var acTimer = null;

        function fetchSugestoes(q) {
            var key = q + '|' + fornecedor;
            if (acCache[key]) { renderSugestoes(acCache[key]); return; }
            var url = '/despesas/items/sugestoes?q=' + encodeURIComponent(q) + '&fornecedor=' + encodeURIComponent(fornecedor);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { acCache[key] = data; renderSugestoes(data); })
                .catch(function () {});
        }

        function renderSugestoes(items) {
            acList.innerHTML = '';
            if (!items.length) { acList.classList.add('hidden'); return; }
            items.forEach(function (s) {
                var li = document.createElement('li');
                li.className = 'cursor-pointer px-3 py-2 text-slate-200 hover:bg-white/10';
                li.textContent = s.descricao + ' (' + s.unidade_compra + ')';
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    document.getElementById('item-descricao').value = s.descricao;
                    var sel = document.getElementById('item-unidade');
                    for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value === s.unidade_compra) { sel.selectedIndex = i; break; }
                    }
                    if (s.preco_medio) {
                        document.getElementById('item-preco').value = Number(s.preco_medio).toFixed(4);
                    }
                    acList.classList.add('hidden');
                    document.getElementById('item-quantidade').focus();
                });
                acList.appendChild(li);
            });
            acList.classList.remove('hidden');
        }

        var descInput = document.getElementById('item-descricao');
        if (descInput) {
            descInput.addEventListener('input', function () {
                clearTimeout(acTimer);
                var q = this.value.trim();
                if (q.length < 1) { acList.classList.add('hidden'); return; }
                acTimer = setTimeout(function () { fetchSugestoes(q); }, 200);
            });
            descInput.addEventListener('blur', function () {
                setTimeout(function () { acList.classList.add('hidden'); }, 150);
            });
            descInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') acList.classList.add('hidden');
            });
        }

        // ── Formulário adicionar produto ──────────────────────────────────────────

        var formAddItem = document.getElementById('form-add-item');
        if (formAddItem) {
            formAddItem.addEventListener('submit', function (e) {
                e.preventDefault();
                var errEl = document.getElementById('add-item-error');
                errEl.classList.add('hidden');

                var desc = document.getElementById('item-descricao').value.trim();
                var qtd  = parseFloat(document.getElementById('item-quantidade').value);
                var unid = document.getElementById('item-unidade').value;
                var preco = parseFloat(document.getElementById('item-preco').value);
                var iva   = parseInt(document.getElementById('item-iva').value, 10);

                if (!desc || isNaN(qtd) || qtd <= 0 || isNaN(preco) || preco < 0) {
                    errEl.textContent = 'Preencha todos os campos obrigatorios.';
                    errEl.classList.remove('hidden');
                    return;
                }

                var btn = document.getElementById('btn-add-item');
                btn.disabled = true;
                btn.textContent = 'A guardar...';

                fetch('/despesas/' + despesaId + '/items', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        descricao: desc,
                        quantidade: qtd,
                        unidade_compra: unid,
                        preco_unitario: preco,
                        iva_percentagem: iva,
                    }),
                }).then(function (r) {
                    if (!r.ok) return r.json().then(function (d) { throw new Error(d.message || 'Erro'); });
                    return r.json();
                }).then(function (data) {
                    addItemRow(data.item);
                    updateTotais(data.totais);
                    // Limpar campos, manter unidade e IVA
                    document.getElementById('item-descricao').value = '';
                    document.getElementById('item-quantidade').value = '';
                    document.getElementById('item-preco').value = '';
                    acCache = {}; // invalidar cache de sugestões
                    showToast(desc + ' adicionado.', 'success');
                    document.getElementById('item-descricao').focus();
                }).catch(function (err) {
                    errEl.textContent = err.message || 'Erro ao guardar.';
                    errEl.classList.remove('hidden');
                }).finally(function () {
                    btn.disabled = false;
                    btn.textContent = '+ Adicionar';
                });
            });
        }
    })();
    </script>
</x-layouts.app>
