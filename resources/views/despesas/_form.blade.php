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

    {{-- Upload de ficheiro / foto --}}
    <div class="mb-5">
        <label class="text-sm text-slate-300">Foto ou scan da fatura
            <div class="mt-1">
                <input type="file" name="ficheiro" id="ficheiro-input" accept="image/*,application/pdf"
                    class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 file:mr-3 file:rounded file:border-0 file:bg-emerald-500/20 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-emerald-300">
            </div>
            <p class="mt-1 text-xs text-slate-500">Em telemovel, escolha a camara neste campo. Ao guardar uma imagem, a IA local recebe um job automaticamente.</p>
            <p id="ficheiro-status" class="mt-2 hidden rounded border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200"></p>
        </label>
        @if($despesa->exists && $despesa->ficheiro_path)
            <p class="mt-1 text-xs text-slate-500">Ficheiro atual: <a href="{{ Storage::disk('public')->url($despesa->ficheiro_path) }}" target="_blank" class="text-blue-400 hover:underline">ver ficheiro</a> (substituir acima para mudar)</p>
        @endif

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
                    <label class="text-xs text-slate-400">Unid./qtd.
                        <input type="number" name="items[__IDX__][unidades_por_quantidade]" value="1" step="0.001" min="0"
                            class="item-fator mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-2 py-1.5 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">Qtd unidades
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
    var maxUploadSide = 1800;
    var jpegQuality = 0.82;
    var compressibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // -- QR Scanner --
    function tryDecodeQr(file) {
        if (typeof jsQR === 'undefined') {
            return;
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
                    var status = document.getElementById('ficheiro-status');
                    if (status) {
                        status.textContent = 'QR AT detetado e campos preenchidos. Reveja os dados e guarde a entrada.';
                    }
                } else {
                    var status = document.getElementById('ficheiro-status');
                    if (status) {
                        status.textContent = 'Foto preparada. Nao consegui ler o QR automaticamente; pode preencher os campos manualmente e guardar.';
                        status.classList.remove('hidden');
                    }
                }
            };
            img.onerror = function () {
                var status = document.getElementById('ficheiro-status');
                if (status) {
                    status.textContent = 'Foto selecionada. Nao consegui preparar a leitura do QR, mas pode guardar a entrada.';
                    status.classList.remove('hidden');
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

    var ficheiroInput = document.getElementById('ficheiro-input');
    if (ficheiroInput) {
        ficheiroInput.addEventListener('change', function () {
            document.getElementById('qr-banner').classList.add('hidden');
            qrData = null;
            var status = document.getElementById('ficheiro-status');

            if (status) {
                status.classList.remove('hidden');
                status.textContent = 'A preparar ficheiro...';
            }

            if (!this.files || !this.files[0]) {
                if (status) {
                    status.classList.add('hidden');
                    status.textContent = '';
                }
                return;
            }

            var input = this;
            var file = input.files[0];

            prepareInvoiceImage(file).then(function (preparedFile) {
                if (preparedFile !== file && typeof DataTransfer !== 'undefined') {
                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(preparedFile);
                    input.files = dataTransfer.files;
                }

                if (status) {
                    status.textContent = preparedFile.type.startsWith('image/')
                        ? 'Foto preparada. A tentar ler o QR AT...'
                        : 'Ficheiro selecionado. Ao guardar, fica anexado a esta entrada.';
                    status.classList.remove('hidden');
                }

                if (preparedFile.type.startsWith('image/')) {
                    tryDecodeQr(preparedFile);
                }
            }).catch(function () {
                if (status) {
                    status.textContent = 'Ficheiro selecionado. Se o upload falhar, tente uma foto mais leve.';
                    status.classList.remove('hidden');
                }
                if (file.type.startsWith('image/')) {
                    tryDecodeQr(file);
                }
            });
        });

        var form = ficheiroInput.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.ficheiroPrepared === '1') {
                    return;
                }

                if (!ficheiroInput.files || !ficheiroInput.files[0] || !ficheiroInput.files[0].type.startsWith('image/')) {
                    form.dataset.ficheiroPrepared = '1';
                    return;
                }

                event.preventDefault();
                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.textContent = 'A preparar foto...';
                }

                prepareInvoiceImage(ficheiroInput.files[0]).then(function (preparedFile) {
                    if (preparedFile && typeof DataTransfer !== 'undefined') {
                        var dataTransfer = new DataTransfer();
                        dataTransfer.items.add(preparedFile);
                        ficheiroInput.files = dataTransfer.files;
                    }
                }).finally(function () {
                    form.dataset.ficheiroPrepared = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(button || undefined);
                    } else {
                        form.submit();
                    }
                });
            });
        }
    }

    function prepareInvoiceImage(file) {
        if (!compressibleTypes.includes(file.type) || file.size < 1024 * 1024) {
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
