@php
    // Os casts decimal:N devolvem strings ("6.00"), que nao batem certo com os
    // value dos <select> ("6"). Converte-se aqui para numero.
    $existingItems = old('items', $despesa->exists ? $despesa->items->map(fn($i) => [
        'descricao' => $i->descricao,
        'quantidade' => (float) $i->quantidade,
        'unidade_compra' => $i->unidade_compra ?? 'un',
        'unidades_por_quantidade' => (float) ($i->unidades_por_quantidade ?? 1),
        'quantidade_unidades' => (float) ($i->quantidade_unidades ?? $i->quantidade),
        'preco_unitario' => (float) $i->preco_unitario,
        'iva_percentagem' => (float) $i->iva_percentagem,
        'notas' => $i->notas ?? '',
    ])->toArray() : []);
@endphp

{{-- Secao: Cabecalho --}}
<div class="mb-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-400">Cabecalho da fatura</h2>

    {{-- Upload de ficheiro / foto --}}
    <div class="mb-5">
        <label for="ficheiro-input" class="block text-sm text-slate-300">Foto ou scan da fatura</label>
        <input type="file" name="ficheiro" id="ficheiro-input" accept="image/*,application/pdf"
            class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 file:mr-3 file:rounded file:border-0 file:bg-emerald-500/20 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-emerald-300">

        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" id="btn-extrair-ia"
                class="rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-600 disabled:opacity-60">
                Ler fatura com IA
            </button>
        </div>

        <p class="mt-1 text-xs text-slate-500">Aceita foto (JPG, PNG, WEBP) ou PDF. No telemovel, escolha a camara neste campo. Depois carregue em "Ler fatura com IA" para preencher as linhas. Se guardar sem o fazer, a leitura corre no servidor e o guardar pode demorar ate meio minuto.</p>
        <p id="ficheiro-status" class="mt-2 hidden rounded border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200"></p>

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
    <p class="mb-3 text-xs text-slate-500">O QR da AT nao traz produtos. Use a leitura por IA ou registe as linhas a mao; a conversao para unidades da logo o custo unitario usado nas margens.</p>

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
    'use strict';

    // O OCR do servidor le esta imagem: abaixo dos ~2500px de lado uma tabela
    // de fatura A4 fica ilegivel e perdem-se linhas.
    var MAX_UPLOAD_SIDE = 2600;
    var MAX_QR_SIDE = 1800;
    var MAX_IA_SIDE = 1600;
    var JPEG_QUALITY = 0.88;
    var COMPRESSIVEIS = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    var ESPERA_MAX_SUBMIT = 6000;

    var fileInput = document.getElementById('ficheiro-input');
    var statusEl = document.getElementById('ficheiro-status');
    var qrBanner = document.getElementById('qr-banner');
    var btnIa = document.getElementById('btn-extrair-ia');
    var itemsContainer = document.getElementById('items-container');
    var template = document.getElementById('item-template');
    var totaisContainer = document.getElementById('totais-container');
    var valorManualContainer = document.getElementById('valor-manual-container');
    var form = fileInput ? fileInput.closest('form') : null;

    var valoresIniciais = {};
    ['campo-titulo', 'campo-numero-fatura', 'campo-fornecedor', 'campo-data', 'campo-valor'].forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) valoresIniciais[id] = campo.value.trim();
    });

    var preparacao = null;        // promessa da preparacao da foto (ou null)
    var preparacaoPendente = false;
    var aEnviar = false;
    var idx = 0;

    // ---------------------------------------------------------------- helpers

    function mostrarStatus(texto) {
        if (!statusEl) return;
        statusEl.textContent = texto;
        statusEl.classList.remove('hidden');
    }

    function esconderStatus() {
        if (!statusEl) return;
        statusEl.textContent = '';
        statusEl.classList.add('hidden');
    }

    function ficheiroAtual() {
        return fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    }

    function substituirFicheiro(novo) {
        if (!novo || !fileInput || typeof DataTransfer === 'undefined') return;
        try {
            var dt = new DataTransfer();
            dt.items.add(novo);
            fileInput.files = dt.files;
        } catch (e) {
            // se o browser nao deixar, fica o ficheiro original
        }
    }

    // Reduz a imagem para nao rebentar com o upload. Resolve sempre — em caso
    // de erro devolve o ficheiro original em vez de ficar pendurada.
    function reduzirImagem(file, maxSide) {
        return new Promise(function (resolve) {
            if (!file || COMPRESSIVEIS.indexOf(file.type) === -1) {
                resolve(file);
                return;
            }

            var terminou = false;
            function acabar(resultado) {
                if (terminou) return;
                terminou = true;
                resolve(resultado || file);
            }

            // rede de seguranca: em telemoveis mais fracos o canvas pode nunca
            // devolver nada. Ao fim de 8s seguimos com o ficheiro original.
            setTimeout(function () { acabar(file); }, 8000);

            try {
                var reader = new FileReader();
                reader.onerror = function () { acabar(file); };
                reader.onload = function (event) {
                    var img = new Image();
                    img.onerror = function () { acabar(file); };
                    img.onload = function () {
                        try {
                            var escala = Math.min(1, maxSide / Math.max(img.width, img.height));
                            var canvas = document.createElement('canvas');
                            canvas.width = Math.max(1, Math.round(img.width * escala));
                            canvas.height = Math.max(1, Math.round(img.height * escala));
                            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                            canvas.toBlob(function (blob) {
                                if (!blob || blob.size >= file.size) {
                                    acabar(file);
                                    return;
                                }
                                acabar(new File([blob], file.name.replace(/\.(png|webp|jpeg)$/i, '.jpg'), {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                }));
                            }, 'image/jpeg', JPEG_QUALITY);
                        } catch (e) {
                            acabar(file);
                        }
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            } catch (e) {
                acabar(file);
            }
        });
    }

    // ------------------------------------------------------------- QR da AT

    function parseAtQr(raw) {
        var map = {};
        raw.split('*').forEach(function (part) {
            var i = part.indexOf(':');
            if (i !== -1) map[part.substring(0, i)] = part.substring(i + 1);
        });
        return map;
    }

    function normalizarNumero(valor) {
        return String(valor === null || valor === undefined ? '' : valor).trim().replace(',', '.');
    }

    // O que o QR da AT preenche fica marcado e a leitura por OCR/IA nao lhe
    // toca: o QR e' a fonte exacta do numero, da data, do NIF e do total.
    function fixarDoQr(id, valor) {
        var campo = document.getElementById(id);

        if (!campo || valor === null || valor === undefined || String(valor).trim() === '') {
            return;
        }

        campo.value = valor;
        campo.dataset.fonteQr = '1';
    }

    function preencherComQr(data) {
        var titulo = document.getElementById('campo-titulo');
        if (titulo && !titulo.value && data['G']) {
            titulo.value = 'Fatura ' + data['G'];
            titulo.dataset.fonteQr = '1';
        }

        if (data['A']) {
            fixarDoQr('campo-fornecedor', data['A']);
        }
        if (data['F'] && data['F'].length === 8) {
            fixarDoQr('campo-data', data['F'].substring(0, 4) + '-' + data['F'].substring(4, 6) + '-' + data['F'].substring(6, 8));
        }
        if (data['G']) {
            fixarDoQr('campo-numero-fatura', data['G']);
        }
        if (data['O']) {
            fixarDoQr('campo-valor', normalizarNumero(data['O']));
        }
        if (data['H']) {
            var notas = document.querySelector('textarea[name="notas"]');
            if (notas && !notas.value) notas.value = 'ATCUD: ' + data['H'];
        }
    }

    function lerQr(file) {
        if (typeof jsQR === 'undefined' || !file || !file.type || file.type.indexOf('image/') !== 0) return;

        try {
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    try {
                        var escala = Math.min(1, MAX_QR_SIDE / Math.max(img.width, img.height));
                        var canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(img.width * escala));
                        canvas.height = Math.max(1, Math.round(img.height * escala));
                        var ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        var code = jsQR(imageData.data, imageData.width, imageData.height);
                        if (code && code.data && code.data.indexOf('A:') === 0) {
                            preencherComQr(parseAtQr(code.data));
                            if (qrBanner) qrBanner.classList.remove('hidden');
                            mostrarStatus('QR AT lido: cabecalho preenchido. Carregue em "Ler fatura com IA" para trazer as linhas dos produtos.');
                        }
                    } catch (e) {
                        // sem QR, segue-se em frente
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } catch (e) {
            // sem QR, segue-se em frente
        }
    }

    // ------------------------------------------------------- linhas da fatura

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
            row.querySelector('.item-custo-unidade').textContent = formatEur(unidades > 0 ? lineSemIva / unidades : 0);
            row.querySelector('.item-total').textContent = formatEur(lineSemIva + lineIva);
            subtotal += lineSemIva;
            ivaTotal += lineIva;
        });
        document.getElementById('total-sem-iva').textContent = formatEur(subtotal);
        document.getElementById('total-iva').textContent = formatEur(ivaTotal);
        document.getElementById('total-com-iva').textContent = formatEur(subtotal + ivaTotal);
    }

    function toggleValorManual() {
        var hasItems = itemsContainer.querySelectorAll('.item-row').length > 0;
        totaisContainer.classList.toggle('hidden', !hasItems);
        valorManualContainer.classList.toggle('hidden', hasItems);
        var campoValor = document.getElementById('campo-valor');
        if (campoValor) campoValor.required = !hasItems;
    }

    // Poe um valor num <select> tolerando formatos ("6.00" -> "6", "KG" -> "kg").
    // Se mesmo assim nao houver opcao, usa o valor por omissao em vez de deixar
    // o campo vazio.
    function escolherOpcao(select, valor, omissao, numerico) {
        if (!select) return;

        var candidatos = [];

        if (valor !== null && valor !== undefined && String(valor).trim() !== '') {
            var bruto = String(valor).trim();
            candidatos.push(bruto);
            candidatos.push(bruto.toLowerCase());

            if (numerico) {
                var n = parseFloat(bruto.replace(',', '.'));
                if (isFinite(n)) {
                    candidatos.push(String(n));
                    candidatos.push(String(Math.round(n)));
                }
            }
        }

        candidatos.push(String(omissao));

        for (var i = 0; i < candidatos.length; i++) {
            select.value = candidatos[i];
            if (select.selectedIndex !== -1 && select.value === candidatos[i]) {
                return;
            }
        }

        select.selectedIndex = 0;
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
            escolherOpcao(row.querySelector('.item-unidade'), values.unidade_compra, 'un', false);
            row.querySelector('.item-fator').value = values.unidades_por_quantidade || 1;
            row.querySelector('.item-unidades').value = values.quantidade_unidades || ((parseFloat(values.quantidade) || 1) * (parseFloat(values.unidades_por_quantidade) || 1));
            row.querySelector('.item-preco').value = values.preco_unitario || 0;
            // O IVA vem da BD como "6.00" (cast decimal:2) e como 6 da leitura.
            // Sem normalizar, o select fica em branco e a linha e' gravada errada.
            escolherOpcao(row.querySelector('.item-iva'), values.iva_percentagem, 23, true);
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

    document.getElementById('btn-add-item').addEventListener('click', function () {
        addRow(null);
    });

    // ------------------------------------------------------------ extracao IA

    // Nao mexe no que veio do QR (mais fiavel) nem no que o utilizador ja
    // escreveu; o valor com que o campo nasceu (a data de hoje, por exemplo)
    // pode ser substituido.
    function setIfPresent(id, valor) {
        var campo = document.getElementById(id);

        if (!campo || campo.dataset.fonteQr === '1') {
            return;
        }

        var atual = campo.value.trim();

        if (atual !== '' && atual !== (valoresIniciais[id] || '')) {
            return;
        }

        if (valor !== null && valor !== undefined && String(valor).trim() !== '' && String(valor) !== '0') {
            campo.value = valor;
        }
    }

    function preencherComIa(data) {
        if (!data) return;

        setIfPresent('campo-titulo', data.titulo);
        setIfPresent('campo-numero-fatura', data.numero_fatura);
        setIfPresent('campo-fornecedor', data.fornecedor);
        setIfPresent('campo-data', data.data);

        if (Array.isArray(data.items) && data.items.length > 0) {
            itemsContainer.querySelectorAll('.item-row').forEach(function (row) { row.remove(); });
            data.items.forEach(function (item) {
                addRow({
                    descricao: item.descricao || '',
                    quantidade: normalizarNumero(item.quantidade || 1),
                    unidade_compra: item.unidade_compra || 'un',
                    unidades_por_quantidade: normalizarNumero(item.unidades_por_quantidade || 1),
                    quantidade_unidades: normalizarNumero(item.quantidade_unidades || item.quantidade || 1),
                    preco_unitario: normalizarNumero(item.preco_unitario || 0),
                    iva_percentagem: item.iva_percentagem || 23,
                    notas: item.notas || ''
                });
            });
            mostrarStatus((data.fonte === 'ocr' ? 'Leitura local' : 'IA') + ': ' + data.items.length + ' linha(s). Confirme os valores (sobretudo o IVA) e guarde.');
        } else {
            setIfPresent('campo-valor', data.valor);
            mostrarStatus(data.aviso || 'Li o cabecalho mas nao encontrei linhas de produtos. Adicione-as a mao.');
        }

        toggleValorManual();
    }

    if (btnIa) {
        btnIa.addEventListener('click', function () {
            var file = ficheiroAtual();

            if (!file) {
                mostrarStatus('Escolha ou tire uma foto da fatura primeiro.');
                return;
            }
            var ehPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');

            if (file.type.indexOf('image/') !== 0 && !ehPdf) {
                mostrarStatus('A leitura por IA aceita fotos (JPG, PNG, WEBP) ou PDF.');
                return;
            }

            var tokenInput = form ? form.querySelector('input[name="_token"]') : null;
            if (!tokenInput) {
                mostrarStatus('Sessao expirada. Recarregue a pagina e tente de novo.');
                return;
            }

            btnIa.disabled = true;
            btnIa.textContent = ehPdf ? 'A enviar PDF...' : 'A preparar foto...';
            mostrarStatus(ehPdf ? 'A enviar o PDF para a IA...' : 'A preparar a foto para a IA...');

            reduzirImagem(file, MAX_IA_SIDE)
                .then(function (ficheiroIa) {
                    var formData = new FormData();
                    formData.append('ficheiro', ficheiroIa, ehPdf ? (file.name || 'fatura.pdf') : 'fatura-ia.jpg');
                    btnIa.textContent = 'A ler fatura...';
                    mostrarStatus('A IA esta a ler a fatura. Pode demorar cerca de meio minuto...');

                    return fetch(@json(route('despesas.extrair-ia')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': tokenInput.value,
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: formData
                    });
                })
                .then(function (response) {
                    return response.text().then(function (text) {
                        var body = {};
                        try {
                            body = text ? JSON.parse(text) : {};
                        } catch (e) {
                            if (!response.ok) {
                                throw new Error('O servidor nao devolveu uma resposta valida. Confirme que a sessao esta iniciada e tente de novo.');
                            }
                        }
                        if (!response.ok) {
                            var erroValidacao = body.errors ? Object.keys(body.errors).map(function (k) { return body.errors[k].join(' '); }).join(' ') : null;
                            throw new Error(body.message || erroValidacao || 'Nao foi possivel ler a fatura.');
                        }
                        return body;
                    });
                })
                .then(preencherComIa)
                .catch(function (error) {
                    mostrarStatus(error && error.message ? error.message : 'Nao foi possivel ler a fatura com IA.');
                })
                .then(function () {
                    btnIa.disabled = false;
                    btnIa.textContent = 'Ler fatura com IA';
                });
        });
    }

    // ------------------------------------------------------- input do ficheiro

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (qrBanner) qrBanner.classList.add('hidden');
            preparacao = null;
            preparacaoPendente = false;

            var file = ficheiroAtual();
            if (!file) {
                esconderStatus();
                return;
            }

            if (file.type.indexOf('image/') !== 0) {
                mostrarStatus('Ficheiro selecionado. Carregue em "Ler fatura com IA" para preencher as linhas, ou guarde já.');
                return;
            }

            mostrarStatus('A preparar a foto...');
            preparacaoPendente = true;

            preparacao = reduzirImagem(file, MAX_UPLOAD_SIDE).then(function (preparado) {
                preparacaoPendente = false;
                try {
                    substituirFicheiro(preparado);
                    mostrarStatus('Foto pronta. Carregue em "Ler fatura com IA" para preencher as linhas, ou guarde ja.');
                    lerQr(ficheiroAtual() || preparado);
                } catch (e) {
                    // nada a fazer: o ficheiro original segue no formulario
                }
                return preparado;
            }, function () {
                preparacaoPendente = false;
            });
        });
    }

    // ------------------------------------------------------------ submissao
    // Nunca bloquear o Guardar: se a preparacao da foto ainda estiver a
    // decorrer espera-se no maximo ESPERA_MAX_SUBMIT e submete-se na mesma.
    function marcarAGuardar() {
        var botao = form ? form.querySelector('button[type="submit"]') : null;
        if (!botao || botao.dataset.aGuardar === '1') return;
        botao.dataset.aGuardar = '1';
        botao.textContent = 'A guardar...';
        botao.style.opacity = '0.7';
        botao.style.pointerEvents = 'none';
    }

    if (form) {
        // O evento submit so dispara depois da validacao do browser passar,
        // por isso e seguro marcar o botao aqui.
        form.addEventListener('submit', function (event) {
            if (aEnviar || !preparacaoPendente || !preparacao) {
                marcarAGuardar();
                return;
            }

            event.preventDefault();

            var botao = form.querySelector('button[type="submit"]');
            var textoOriginal = botao ? botao.textContent : null;
            if (botao) botao.textContent = 'A preparar foto...';

            var enviado = false;
            function enviar() {
                if (enviado) return;
                enviado = true;
                aEnviar = true;
                if (botao && textoOriginal !== null) botao.textContent = textoOriginal;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }

            setTimeout(enviar, ESPERA_MAX_SUBMIT);
            Promise.resolve(preparacao).then(enviar, enviar);
        });
    }

    // -------------------------------------------------- linhas ja existentes

    var existing = @json($existingItems);
    existing.forEach(function (item) {
        addRow(item);
    });

    toggleValorManual();
})();
</script>
