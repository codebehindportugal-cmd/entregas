@php
    $existingItems = old('items', $despesa->exists ? $despesa->items->map(fn($i) => [
        'descricao' => $i->descricao,
        'quantidade' => $i->quantidade,
        'unidade_compra' => $i->unidade_compra ?? 'un',
        'unidades_por_quantidade' => $i->unidades_por_quantidade ?? 1,
        'quantidade_unidades' => $i->quantidade_unidades ?? $i->quantidade,
        'preco_unitario' => $i->preco_unitario,
        'iva_percentagem' => $i->iva_percentagem,
        'notas' => $i->notas ?? '',
    ])->toArray() : []);
@endphp

{{-- Secao: Cabecalho --}}
<div class="mb-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-400">Cabecalho da fatura</h2>

    {{-- Upload de multiplas fotos --}}
    <div class="mb-5">
        <p class="mb-2 text-xs text-slate-400">Fotos da fatura — podes adicionar varias folhas</p>

        {{-- Grid de fotos: existentes + novas + botao adicionar --}}
        <div id="fotos-grid" class="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
            @if($despesa->exists)
                @foreach($despesa->fotos as $foto)
                <div class="foto-item group relative" data-foto-id="{{ $foto->id }}">
                    <a href="{{ $foto->url }}" target="_blank">
                        <img src="{{ $foto->url }}"
                            class="h-24 w-full rounded-lg border border-white/10 object-cover transition hover:opacity-80"
                            title="Ver foto">
                    </a>
                    <button type="button" class="btn-delete-foto absolute right-1 top-1 hidden h-6 w-6 items-center justify-center rounded-full bg-red-600/80 text-xs font-bold text-white hover:bg-red-600 group-hover:flex">&times;</button>
                </div>
                @endforeach
                @if($despesa->ficheiro_path && $despesa->fotos->isEmpty())
                @php($legacyUrl = route('public-files.show', ['path' => $despesa->ficheiro_path]))
                <div class="foto-item group relative" data-legacy="1">
                    <a href="{{ $legacyUrl }}" target="_blank">
                        <img src="{{ $legacyUrl }}"
                            class="h-24 w-full rounded-lg border border-amber-500/30 object-cover opacity-80"
                            title="Foto anterior">
                    </a>
                    <span class="absolute bottom-0 left-0 right-0 rounded-b-lg bg-black/60 py-0.5 text-center text-xs text-amber-300">antigo</span>
                </div>
                @endif
            @endif

            {{-- Botao adicionar --}}
            <button type="button" id="btn-add-foto"
                class="flex h-24 w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-white/20 bg-[#0A0F1A] text-slate-400 transition hover:border-emerald-500/40 hover:text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-xs">Adicionar</span>
            </button>
        </div>

        {{-- Input oculto (multiple) --}}
        <input type="file" name="fotos[]" id="fotos-input" accept="image/*,application/pdf" multiple class="hidden">

        {{-- Status do scan --}}
        <p id="ficheiro-status" class="mt-2 hidden rounded border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200"></p>

        {{-- Banner IA --}}
        <div id="ai-banner" class="mt-3 hidden rounded border border-blue-400/30 bg-blue-500/10 p-3 text-sm text-blue-200">
            <strong>IA a processar as fotos...</strong> Os produtos serao adicionados automaticamente em breve.
            <a href="{{ route('despesas.index') }}" class="ml-2 text-xs text-blue-300 underline">Verificar resultados</a>
        </div>

        {{-- Banner QR AT --}}
        <div id="qr-banner" class="mt-3 hidden rounded border border-amber-400/30 bg-amber-500/10 p-3 text-sm text-amber-200">
            <strong>QR AT detectado</strong> — preenche cabecalho e totais. O QR da AT nao inclui produtos/linhas.
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="col-span-2 text-sm text-slate-300 sm:col-span-1">Titulo *
            <input type="text" name="titulo" id="campo-titulo" value="{{ old('titulo', $despesa->titulo) }}" required
                class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Numero de fatura
            <input type="text" name="numero_fatura" id="campo-numero-fatura" value="{{ old('numero_fatura', $despesa->numero_fatura) }}"
                class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Fornecedor / NIF
            <input type="text" name="fornecedor" id="campo-fornecedor" value="{{ old('fornecedor', $despesa->fornecedor) }}"
                class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Data *
            <input type="date" name="data" id="campo-data" value="{{ old('data', $despesa->data?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required
                class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
    </div>
    <div class="mt-4">
        <label class="text-sm text-slate-300">Notas
            <textarea name="notas" rows="2" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('notas', $despesa->notas) }}</textarea>
        </label>
    </div>
</div>

{{-- Secao: Linhas da fatura --}}
<div class="mb-6">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Linhas da fatura</h2>
        <button type="button" id="btn-add-item"
            class="rounded border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20">
            + Adicionar linha
        </button>
    </div>
    <p class="mb-3 text-xs text-slate-500">O QR da AT nao traz produtos. Registe as linhas da fatura e use a conversao para unidades para obter logo o custo unitario usado nas margens.</p>

    <div id="items-container" class="space-y-3">
        {{-- Template vazio (oculto) --}}
        <template id="item-template">
            <div class="item-row rounded border border-white/10 bg-[#0A0F1A] p-3">
                <div class="grid gap-3 lg:grid-cols-[2fr_.8fr_.8fr_.9fr_.9fr_.9fr_.8fr_auto]">
                    <label class="text-xs text-slate-400">Descricao *
                        <input type="text" name="items[__IDX__][descricao]" required
                            class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">Quantidade
                        <input type="number" name="items[__IDX__][quantidade]" value="1" step="0.001" min="0.001"
                            class="item-qtd mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">Unid. compra
                        <select name="items[__IDX__][unidade_compra]" class="item-unidade mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                            <option value="un">un</option>
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                            <option value="cx">cx</option>
                            <option value="emb">emb</option>
                            <option value="molho">molho</option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-400" title="Quantas unidades individuais tem cada unidade de compra. Ex: 5 bananas por kg → 5">Unid. por kg/cx
                        <input type="number" name="items[__IDX__][unidades_por_quantidade]" value="1" step="0.001" min="0"
                            class="item-fator mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400" title="Calculado automaticamente: Quantidade × Unid. por kg/cx">Qtd unidades
                        <input type="number" name="items[__IDX__][quantidade_unidades]" value="1" step="0.001" min="0"
                            class="item-unidades mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">Preco unit. (EUR)
                        <input type="number" name="items[__IDX__][preco_unitario]" value="0" step="0.0001" min="0"
                            class="item-preco mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">IVA %
                        <select name="items[__IDX__][iva_percentagem]" class="item-iva mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                            @foreach($taxasIva as $taxa)
                                <option value="{{ $taxa }}" @if($taxa == 23) selected @endif>{{ $taxa }}%</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-end pb-0.5">
                        <button type="button" class="btn-remove-item rounded bg-red-500/10 px-2 py-1.5 text-xs text-red-400 hover:bg-red-500/20">X</button>
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <label class="flex-1 text-xs text-slate-400">Notas
                        <input type="text" name="items[__IDX__][notas]"
                            class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <div class="mt-4 shrink-0 text-right text-xs text-slate-400">
                        Custo/unid. s/ IVA: <span class="item-custo-unidade font-semibold text-emerald-300">0,00 EUR</span>
                        <span class="mx-2 text-slate-600">|</span>
                        Total c/ IVA: <span class="item-total font-semibold text-white">0,00 EUR</span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Totais --}}
    <div id="totais-container" class="{{ count($existingItems) > 0 ? '' : 'hidden' }} mt-3 rounded border border-white/10 bg-[#0A0F1A] p-3 text-right text-sm">
        <span class="text-slate-400">Subtotal s/ IVA: <span id="total-sem-iva" class="font-semibold text-white">0,00 EUR</span></span>
        <span class="mx-4 text-slate-400">IVA: <span id="total-iva" class="font-semibold text-white">0,00 EUR</span></span>
        <span class="text-slate-300">Total c/ IVA: <span id="total-com-iva" class="text-lg font-bold text-emerald-400">0,00 EUR</span></span>
    </div>

    {{-- Valor manual (visivel se sem linhas) --}}
    <div id="valor-manual-container" class="{{ count($existingItems) > 0 ? 'hidden' : '' }} mt-4">
        <label class="text-sm text-slate-300">Valor total (EUR) *
            <input type="number" name="valor" id="campo-valor" value="{{ old('valor', $despesa->exists ? (string) $despesa->valor : '') }}"
                step="0.01" min="0"
                class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white sm:w-64">
        </label>
    </div>
</div>

{{-- jsQR para leitura de QR da AT --}}
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
(function () {
    var qrData = null;
    var maxQrSide = 1800;
    var maxUploadSide = 1400;
    var jpegQuality = 0.78;
    var compressibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // -- Toast system --
    function showToast(msg, type) {
        var toast = document.createElement('div');
        var base = 'fixed right-4 top-4 z-[9999] max-w-xs rounded-lg border px-4 py-3 text-sm font-medium shadow-xl transition-opacity duration-300';
        var colors = type === 'success'
            ? 'border-emerald-400/50 bg-emerald-600 text-white'
            : 'border-amber-400/50 bg-amber-500 text-white';
        toast.className = base + ' ' + colors;
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
        }, 3800);
    }

    // -- Gestao de multiplas fotos --
    var fotosNovas = new Map();
    var fotoCounter = 0;

    function adicionarFotoAoGrid(file) {
        var uuid = 'nova-' + (++fotoCounter);
        fotosNovas.set(uuid, file);

        var grid = document.getElementById('fotos-grid');
        var addBtn = document.getElementById('btn-add-foto');

        var div = document.createElement('div');
        div.className = 'foto-item group relative';
        div.dataset.uuid = uuid;

        var img = document.createElement('img');
        img.className = 'h-24 w-full rounded-lg border border-emerald-500/30 object-cover';
        var reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; };
        reader.readAsDataURL(file);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'absolute right-1 top-1 hidden h-6 w-6 items-center justify-center rounded-full bg-red-600/80 text-xs font-bold text-white hover:bg-red-600 group-hover:flex';
        btn.innerHTML = '&times;';
        btn.addEventListener('click', function () {
            fotosNovas.delete(uuid);
            div.remove();
            atualizarAiBanner();
        });

        div.appendChild(img);
        div.appendChild(btn);
        grid.insertBefore(div, addBtn);

        atualizarAiBanner();
    }

    function atualizarAiBanner() {
        var aiBanner = document.getElementById('ai-banner');
        if (!aiBanner) return;
        var temImagens = false;
        fotosNovas.forEach(function (f) {
            if (f.type.startsWith('image/')) temImagens = true;
        });
        aiBanner.classList.toggle('hidden', !temImagens);
    }

    function processarFicheiros(files) {
        var primeiraImagem = true;
        Array.from(files).forEach(function (file) {
            adicionarFotoAoGrid(file);
            if (primeiraImagem && file.type.startsWith('image/')) {
                tryDecodeQr(file);
                primeiraImagem = false;
            }
        });
    }

    // -- QR Scanner --
    function tryDecodeQr(file) {
        if (typeof jsQR === 'undefined') {
            return;
        }

        var status = document.getElementById('ficheiro-status');
        if (status) {
            status.textContent = 'A ler QR code...';
            status.classList.remove('hidden');
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var scale = Math.min(1, maxQrSide / Math.max(img.width, img.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.width * scale));
                canvas.height = Math.max(1, Math.round(img.height * scale));
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code && code.data && code.data.startsWith('A:')) {
                    qrData = parseAtQr(code.data);
                    preencherComQr(qrData);
                    document.getElementById('qr-banner').classList.remove('hidden');
                    var st = document.getElementById('ficheiro-status');
                    if (st) {
                        st.textContent = 'QR AT detetado e campos preenchidos. Reveja os dados e guarde.';
                    }
                    showToast('QR AT detectado! Campos preenchidos automaticamente.', 'success');
                } else {
                    var st = document.getElementById('ficheiro-status');
                    if (st) {
                        st.textContent = 'Foto preparada. Preenche os campos manualmente e guarda.';
                        st.classList.remove('hidden');
                    }
                    showToast('QR nao detectado. Preenche os campos manualmente.', 'warn');
                }
            };
            img.onerror = function () {
                var st = document.getElementById('ficheiro-status');
                if (st) {
                    st.textContent = 'Foto selecionada. Guarda para anexar a esta entrada.';
                    st.classList.remove('hidden');
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function parseAtQr(raw) {
        var parts = raw.split('*');
        var map = {};
        parts.forEach(function (part) {
            var idx = part.indexOf(':');
            if (idx !== -1) {
                map[part.substring(0, idx)] = part.substring(idx + 1);
            }
        });
        return map;
    }

    function preencherComQr(data) {
        // G = numero documento (ex: FT A/123) — usa como título se vazio
        var titulo = document.getElementById('campo-titulo');
        if (titulo && !titulo.value && data['G']) {
            titulo.value = 'Fatura ' + data['G'];
        }
        // A = NIF do emitente
        if (data['A']) {
            var fornecedor = document.getElementById('campo-fornecedor');
            if (fornecedor) fornecedor.value = data['A'];
        }
        // F = data YYYYMMDD → YYYY-MM-DD
        if (data['F'] && data['F'].length === 8) {
            var dataField = document.getElementById('campo-data');
            if (dataField) {
                dataField.value = data['F'].substring(0, 4) + '-' + data['F'].substring(4, 6) + '-' + data['F'].substring(6, 8);
            }
        }
        // G = numero da fatura
        if (data['G']) {
            var numFat = document.getElementById('campo-numero-fatura');
            if (numFat) numFat.value = data['G'];
        }
        // O = total com IVA
        if (data['O']) {
            var campoValor = document.getElementById('campo-valor');
            if (campoValor) campoValor.value = normalizarNumeroQr(data['O']);
        }
        // H = ATCUD (mostra nas notas se existir)
        if (data['H']) {
            var notas = document.querySelector('textarea[name="notas"]');
            if (notas && !notas.value) notas.value = 'ATCUD: ' + data['H'];
        }
    }

    function normalizarNumeroQr(valor) {
        return String(valor || '').trim().replace(',', '.');
    }

    // -- Upload de multiplas fotos --
    var fotosInput = document.getElementById('fotos-input');

    if (document.getElementById('btn-add-foto')) {
        document.getElementById('btn-add-foto').addEventListener('click', function () {
            if (fotosInput) fotosInput.click();
        });
    }

    if (fotosInput) {
        fotosInput.addEventListener('change', function () {
            if (!this.files || !this.files.length) return;
            processarFicheiros(this.files);
            this.value = '';
        });
    }

    // Delete de foto existente (AJAX)
    var csrfToken = (document.querySelector('input[name="_token"]') || {}).value || '';
    document.querySelectorAll('.btn-delete-foto').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.foto-item');
            var fotoId = item ? item.dataset.fotoId : null;
            if (!fotoId) return;
            fetch('/despesas/fotos/' + fotoId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }).then(function (r) {
                if (r.ok) { if (item) item.remove(); showToast('Foto removida.', 'success'); }
                else showToast('Erro ao remover foto.', 'warn');
            }).catch(function () { showToast('Erro ao remover foto.', 'warn'); });
        });
    });

    // Submissao: comprime fotos novas e injeta no input
    var formEl = fotosInput ? fotosInput.closest('form') : null;
    if (formEl && fotosInput) {
        formEl.addEventListener('submit', function (event) {
            if (formEl.dataset.fotosPreparadas === '1') return;

            if (fotosNovas.size === 0) {
                formEl.dataset.fotosPreparadas = '1';
                return;
            }

            event.preventDefault();
            var submitBtn = formEl.querySelector('button[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'A preparar fotos...'; }

            var files = Array.from(fotosNovas.values());
            Promise.all(files.map(prepareInvoiceImage)).then(function (prepared) {
                var dt = new DataTransfer();
                prepared.forEach(function (f) { dt.items.add(f); });
                fotosInput.files = dt.files;
            }).catch(function () {
                var dt = new DataTransfer();
                files.forEach(function (f) { dt.items.add(f); });
                fotosInput.files = dt.files;
            }).finally(function () {
                formEl.dataset.fotosPreparadas = '1';
                if (submitBtn) { submitBtn.disabled = false; }
                formEl.submit();
            });
        });
    }

    function prepareInvoiceImage(file) {
        if (!compressibleTypes.includes(file.type)) {
            return Promise.resolve(file);
        }

        return new Promise(function (resolve, reject) {
            var reader = new FileReader();

            reader.onerror = function () {
                reject(new Error('Nao foi possivel ler a imagem.'));
            };

            reader.onload = function (event) {
                var img = new Image();

                img.onerror = function () {
                    reject(new Error('Nao foi possivel preparar a imagem.'));
                };

                img.onload = function () {
                    var scale = Math.min(1, maxUploadSide / Math.max(img.width, img.height));
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(img.width * scale));
                    canvas.height = Math.max(1, Math.round(img.height * scale));

                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            reject(new Error('Nao foi possivel comprimir a imagem.'));
                            return;
                        }

                        if (blob.size >= file.size) {
                            resolve(file);
                            return;
                        }

                        resolve(new File([blob], file.name.replace(/\.(png|webp)$/i, '.jpg'), {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        }));
                    }, 'image/jpeg', jpegQuality);
                };

                img.src = event.target.result;
            };

            reader.readAsDataURL(file);
        });
    }

    var qrDispensar = document.getElementById('qr-dispensar');
    if (qrDispensar) {
        qrDispensar.addEventListener('click', function () {
            document.getElementById('qr-banner').classList.add('hidden');
            qrData = null;
        });
    }

    // -- Item rows --
    var itemsContainer = document.getElementById('items-container');
    var template = document.getElementById('item-template');
    var totaisContainer = document.getElementById('totais-container');
    var valorManualContainer = document.getElementById('valor-manual-container');
    var idx = 0;

    function formatEur(val) {
        return val.toFixed(2).replace('.', ',') + ' EUR';
    }

    function recalcularTotais() {
        var rows = itemsContainer.querySelectorAll('.item-row');
        var subtotal = 0, ivaTotal = 0;
        rows.forEach(function (row) {
            var qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
            var preco = parseFloat(row.querySelector('.item-preco').value) || 0;
            var iva = parseFloat(row.querySelector('.item-iva').value) || 0;
            var unidades = parseFloat(row.querySelector('.item-unidades').value) || 0;
            var lineSemIva = qtd * preco;
            var lineIva = lineSemIva * (iva / 100);
            var lineTotal = lineSemIva + lineIva;
            var custoUnidade = unidades > 0 ? lineSemIva / unidades : 0;
            row.querySelector('.item-custo-unidade').textContent = formatEur(custoUnidade);
            row.querySelector('.item-total').textContent = formatEur(lineTotal);
            subtotal += lineSemIva;
            ivaTotal += lineIva;
        });
        document.getElementById('total-sem-iva').textContent = formatEur(subtotal);
        document.getElementById('total-iva').textContent = formatEur(ivaTotal);
        document.getElementById('total-com-iva').textContent = formatEur(subtotal + ivaTotal);
    }

    function addRow(values) {
        var html = template.innerHTML.replace(/__IDX__/g, idx);
        idx++;
        var div = document.createElement('div');
        div.innerHTML = html;
        var row = div.firstElementChild;

        if (values) {
            row.querySelector('[name$="[descricao]"]').value = values.descricao || '';
            row.querySelector('.item-qtd').value = values.quantidade || 1;
            row.querySelector('.item-unidade').value = values.unidade_compra || 'un';
            row.querySelector('.item-fator').value = values.unidades_por_quantidade || 1;
            row.querySelector('.item-unidades').value = values.quantidade_unidades || ((parseFloat(values.quantidade) || 1) * (parseFloat(values.unidades_por_quantidade) || 1));
            row.querySelector('.item-preco').value = values.preco_unitario || 0;
            var ivaSelect = row.querySelector('.item-iva');
            if (ivaSelect) ivaSelect.value = values.iva_percentagem || 23;
            var notasInput = row.querySelector('[name$="[notas]"]');
            if (notasInput) notasInput.value = values.notas || '';
        }

        row.querySelector('.btn-remove-item').addEventListener('click', function () {
            row.remove();
            recalcularTotais();
            toggleValorManual();
        });

        function recalcularUnidades() {
            var qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
            var fator = parseFloat(row.querySelector('.item-fator').value) || 0;
            row.querySelector('.item-unidades').value = (qtd * fator).toFixed(3);
            recalcularTotais();
        }

        row.querySelectorAll('.item-qtd, .item-fator').forEach(function (input) {
            input.addEventListener('input', recalcularUnidades);
        });

        row.querySelectorAll('.item-unidades, .item-preco, .item-iva').forEach(function (input) {
            input.addEventListener('input', recalcularTotais);
        });

        itemsContainer.appendChild(row);
        recalcularTotais();
        toggleValorManual();
    }

    function toggleValorManual() {
        var hasItems = itemsContainer.querySelectorAll('.item-row').length > 0;
        totaisContainer.classList.toggle('hidden', !hasItems);
        valorManualContainer.classList.toggle('hidden', hasItems);
        var campoValor = document.getElementById('campo-valor');
        if (campoValor) campoValor.required = !hasItems;
    }

    document.getElementById('btn-add-item').addEventListener('click', function () {
        addRow(null);
    });

    // Pre-popular linhas existentes (editar)
    var existing = @json($existingItems);
    existing.forEach(function (item) {
        addRow(item);
    });

    toggleValorManual();
})();
</script>
