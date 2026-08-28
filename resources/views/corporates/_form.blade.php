@php
    $dias = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'];
    $frutas = ['banana' => 'Banana', 'maca' => 'Maca', 'pera' => 'Pera', 'laranja' => 'Laranja', 'uvas' => 'Uvas', 'fruta_epoca' => 'Fruta epoca'];
    $outrosProdutos = ['frutos_secos' => 'Frutos secos', 'mirtilos' => 'Mirtilos', 'framboesas' => 'Framboesas', 'amoras' => 'Amoras', 'morangos' => 'Morangos'];
    $padaria = ['pao_mistura' => 'Pao de mistura', 'pao_forma' => 'Pao de forma', 'croissant' => 'Croissants', 'bolo' => 'Bolos'];
    $produtosKg = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];
    $diasSelecionados = old('dias_entrega', $corporate->dias_entrega ?? []);
    $frutasPorDiaValores = old('frutas_por_dia', $corporate->frutas_por_dia ?? []);
    $padariaPorDiaValores = old('pastelaria_por_dia', $corporate->pastelaria_por_dia ?? []);
    $produtosMensais = old('produtos_mensais', $corporate->produtos_mensais ?? []);
@endphp
<div class="mb-5 flex flex-wrap gap-1 rounded border border-white/10 bg-[#0A0F1A] p-1" data-form-tabs>
    <button type="button" data-form-tab="empresa" class="rounded px-4 py-2 text-sm font-semibold">Empresa</button>
    <button type="button" data-form-tab="faturacao" class="rounded px-4 py-2 text-sm font-semibold">Faturacao</button>
    <button type="button" data-form-tab="entregas" class="rounded px-4 py-2 text-sm font-semibold">Entregas e produtos</button>
</div>

<div data-form-panel="empresa">
<div class="grid gap-4 lg:grid-cols-2">
    <label class="text-sm text-slate-300">Empresa
        <input name="empresa" required value="{{ old('empresa', $corporate->empresa) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Sucursal
        <input name="sucursal" value="{{ old('sucursal', $corporate->sucursal) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300 lg:col-span-2">Morada da sucursal / entrega
        <input name="morada_entrega" value="{{ old('morada_entrega', $corporate->moradaParaEntrega()) }}" placeholder="Morada usada pelo colaborador para navegar ate a entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Codigo postal (entrega)
        <input name="cp_entrega" value="{{ old('cp_entrega', $corporate->cp_entrega) }}" placeholder="Ex.: 3500-885" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        <span class="mt-1 block text-xs text-slate-500">Usado no local de descarga da guia de transporte. Vazio = extraido da morada.</span>
    </label>
    <label class="text-sm text-slate-300">Cidade (entrega)
        <input name="cidade_entrega" value="{{ old('cidade_entrega', $corporate->cidade_entrega) }}" placeholder="Ex.: Viseu" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Periodicidade
        <select name="periodicidade_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            <option value="semanal" @selected(old('periodicidade_entrega', $corporate->periodicidade_entrega ?? 'semanal') === 'semanal')>Semanal</option>
            <option value="quinzenal" @selected(old('periodicidade_entrega', $corporate->periodicidade_entrega ?? 'semanal') === 'quinzenal')>De 15 em 15 dias</option>
        </select>
    </label>
    <label class="text-sm text-slate-300">Referencia quinzenal
        <input name="quinzenal_referencia" type="date" value="{{ old('quinzenal_referencia', optional($corporate->quinzenal_referencia)->format('Y-m-d')) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Horario
        <input name="horario_entrega" value="{{ old('horario_entrega', $corporate->horario_entrega) }}" placeholder="09:00-11:00" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Responsavel
        <input name="responsavel_nome" value="{{ old('responsavel_nome', $corporate->responsavel_nome) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Telefone
        <input name="responsavel_telefone" value="{{ old('responsavel_telefone', $corporate->responsavel_telefone) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Numero caixas
        <input name="numero_caixas" type="number" min="0" value="{{ old('numero_caixas', $corporate->numero_caixas ?? 1) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Alteracoes validas a partir de
        <input name="configuracao_ativa_desde" type="date" value="{{ old('configuracao_ativa_desde', now()->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        <span class="mt-1 block text-xs text-slate-500">Usado no mapa mensal para manter corretas as quantidades antes e depois da alteracao.</span>
    </label>
</div>
<label class="mt-5 block text-sm text-slate-300">Notas
    <textarea name="notas" rows="4" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('notas', $corporate->notas) }}</textarea>
</label>
<label class="mt-4 flex items-center gap-2 text-sm text-slate-300">
    <input name="ativo" value="1" type="checkbox" @checked(old('ativo', $corporate->ativo ?? true))> Ativo
</label>

</div>

<div data-form-panel="faturacao">
<div class="grid gap-4 lg:grid-cols-2">
    <label class="text-sm text-slate-300">Nome faturacao
        <input name="fatura_nome" value="{{ old('fatura_nome', $corporate->fatura_nome) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">NIF
        <input name="fatura_nif" value="{{ old('fatura_nif', $corporate->fatura_nif) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Email faturacao
        <input name="fatura_email" type="email" value="{{ old('fatura_email', $corporate->fatura_email) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300 lg:col-span-2">Morada faturacao
        <input name="fatura_morada" value="{{ old('fatura_morada', $corporate->fatura_morada) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Preco venda por peca (EUR, SEM IVA)
        <input name="preco_venda_peca" type="number" min="0" step="0.0001" value="{{ old('preco_venda_peca', $corporate->preco_venda_peca) }}" placeholder="Ex: 0.4300" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        <span class="mt-1 block text-xs text-slate-500">Preco LIQUIDO (sem IVA) de cada peca de fruta desta empresa. O IVA (6%) e acrescentado na fatura. Ex.: 0,43 x 560 pecas = 240,00 de incidencia + IVA = 254,40 a pagar.</span>
    </label>
</div>
{{-- Faturacao: aplica-se a TODAS as empresas, tenham ou nao cabaz do catalogo. --}}
<div class="mt-5 rounded border border-white/10 bg-[#0A0F1A] p-4">
    <p class="mb-1 text-sm font-semibold text-white">Faturacao</p>
    <p class="mb-4 text-xs text-slate-500">Valores e referencias usados na fatura e na guia Moloni desta empresa.</p>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="text-sm text-slate-300">Valor acordado por ciclo (4 semanas, EUR c/ IVA)
            <input name="valor_ciclo" type="number" min="0" step="0.01" value="{{ old('valor_ciclo', $corporate->valor_ciclo) }}" placeholder="Ex: 254.40" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Total da fatura mensal desta empresa, tal como acordado. Manda em tudo o resto: se estiver preenchido, e este o valor da linha do cabaz.</span>
        </label>
        <label class="text-sm text-slate-300">Custo de envio por entrega (EUR)
            <input name="custo_envio" type="number" min="0" step="0.01" value="{{ old('custo_envio', $corporate->custo_envio) }}" placeholder="Ex: 7.50" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Linha propria na fatura (IVA 23%), multiplicada pelas entregas do ciclo. Vazio = usa o valor por defeito das Definicoes Moloni; escrever 0 isenta esta empresa.</span>
        </label>
        <label class="text-sm text-slate-300">Preco do cabaz (EUR, c/ IVA)
            <input name="preco_cabaz" type="number" min="0" step="0.01" value="{{ old('preco_cabaz', $corporate->preco_cabaz) }}" placeholder="Ex: 12.50" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Preco acordado por cabaz/entrega. So usado quando o valor por ciclo esta vazio.</span>
        </label>
        <label class="text-sm text-slate-300">Inicio do ciclo de faturacao
            <input name="ciclo_inicio" type="date" value="{{ old('ciclo_inicio', optional($corporate->ciclo_inicio)->format('Y-m-d')) }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">A partir daqui conta o ciclo de 4 semanas (referencia interna da fatura).</span>
        </label>
        <label class="text-sm text-slate-300">Referencia do cliente
            <input name="referencia_cliente" type="text" value="{{ old('referencia_cliente', $corporate->referencia_cliente) }}" placeholder="Ex.: nr. de fornecedor" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Vai no campo 'A sua referencia' (V/Ref.) do Moloni. Pode trocar-se ao faturar.</span>
        </label>
        <label class="text-sm text-slate-300">Dias de vencimento
            <input name="dias_vencimento" type="number" min="0" max="365" value="{{ old('dias_vencimento', $corporate->dias_vencimento) }}" placeholder="Ex.: 15" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Prazo de pagamento na fatura. Vazio = usa o valor por defeito das definicoes.</span>
        </label>
        <label class="text-sm text-slate-300">Referencia do artigo composto (Moloni)
            <input name="moloni_composto_ref" type="text" value="{{ old('moloni_composto_ref', $corporate->moloni_composto_ref) }}" placeholder="Ex.: HM5069-0" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Cabaz composto a usar na fatura. Vazio = usa o das definicoes.</span>
        </label>
        <label class="text-sm text-slate-300">Referencia do artigo da guia (Moloni)
            <input name="moloni_guia_ref" type="text" value="{{ old('moloni_guia_ref', $corporate->moloni_guia_ref) }}" placeholder="Ex.: HM5069-0" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
            <span class="mt-1 block text-xs text-slate-500">Artigo usado na guia de transporte. Vazio = usa o das definicoes ou o composto.</span>
        </label>
    </div>
</div>
<div class="mt-5 rounded border border-white/10 bg-[#0A0F1A] p-4" data-cabaz-corporate>
    <label class="flex items-start gap-3 text-sm text-slate-300">
        <input type="checkbox" class="mt-1" data-cabaz-toggle @checked(filled(old('cabaz_tipo', $corporate->cabaz_tipo)))>
        <span>
            <span class="block font-semibold text-white">Esta empresa recebe cabazes do catalogo</span>
            <span class="mt-1 block text-xs text-slate-500">Se definido, este tipo de cabaz entra nos calculos das listas semanais. Se nao estiver definido, continuam a ser usadas as frutas individuais abaixo.</span>
        </span>
    </label>
    <div class="mt-4 grid gap-4 md:grid-cols-2" data-cabaz-fields>
        <label class="text-sm text-slate-300">Tipo de cabaz
            <select name="cabaz_tipo" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
                <option value="">Sem tipo de cabaz</option>
                <option value="pequeno" @selected(old('cabaz_tipo', $corporate->cabaz_tipo) === 'pequeno')>Pequeno</option>
                <option value="medio" @selected(old('cabaz_tipo', $corporate->cabaz_tipo) === 'medio')>Medio</option>
                <option value="grande" @selected(old('cabaz_tipo', $corporate->cabaz_tipo) === 'grande')>Grande</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Quantidade por entrega
            <input name="cabaz_quantidade" type="number" min="1" value="{{ old('cabaz_quantidade', $corporate->cabaz_quantidade ?? 1) }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-white">
        </label>
    </div>
</div>

</div>

<div data-form-panel="entregas">
<div class="mt-5">
    <p class="mb-2 text-sm font-medium text-slate-300">Dias de entrega</p>
    <div class="flex flex-wrap gap-2">
        @foreach($dias as $dia)
            <label class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200">
                <input name="dias_entrega[]" type="checkbox" value="{{ $dia }}" @checked(in_array($dia, $diasSelecionados, true))> {{ $dia }}
            </label>
        @endforeach
    </div>
</div>
<div class="mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-300">Produtos por dia da semana</p>
            <p class="mt-1 text-xs text-slate-500">Preencha fruta e outros produtos apenas nos dias em que esta empresa recebe entrega.</p>
        </div>
    </div>
    <div class="mt-4 inline-flex rounded border border-white/10 bg-[#0A0F1A] p-1" data-product-tabs>
        <button type="button" data-product-tab="fruta" class="rounded px-4 py-2 text-sm font-semibold">Fruta</button>
        <button type="button" data-product-tab="outros" class="rounded px-4 py-2 text-sm font-semibold">Outros produtos</button>
        <button type="button" data-product-tab="padaria" class="rounded px-4 py-2 text-sm font-semibold">Padaria</button>
    </div>
    <div class="mt-3 space-y-4">
        @foreach($dias as $dia)
            <div class="rounded border border-white/10 bg-[#0A0F1A] p-4" data-day-panel="{{ $dia }}">
                <p class="mb-3 text-sm font-semibold text-white">{{ $dia }}</p>
                <div data-product-panel="fruta">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        @foreach($frutas as $key => $label)
                            <label class="text-xs text-slate-400">{{ $label }}
                                <input name="frutas_por_dia[{{ $dia }}][{{ $key }}]" data-fruit-day="{{ $key }}" type="number" min="0" step="{{ in_array($key, $produtosKg, true) ? '0.01' : '1' }}" value="{{ $frutasPorDiaValores[$dia][$key] ?? 0 }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-white">
                                @if(in_array($key, $produtosKg, true))
                                    <span class="mt-1 block text-[11px] text-slate-500">kg</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
                <div data-product-panel="outros" class="hidden">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach($outrosProdutos as $key => $label)
                            <label class="text-xs text-slate-400">{{ $label }}
                                <input name="frutas_por_dia[{{ $dia }}][{{ $key }}]" data-fruit-day="{{ $key }}" type="number" min="0" step="0.01" value="{{ $frutasPorDiaValores[$dia][$key] ?? 0 }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-white">
                                <span class="mt-1 block text-[11px] text-slate-500">kg</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div data-product-panel="padaria" class="hidden">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($padaria as $key => $label)
                            <label class="text-xs text-slate-400">{{ $label }}
                                <input name="pastelaria_por_dia[{{ $dia }}][{{ $key }}]" type="number" min="0" step="1" value="{{ $padariaPorDiaValores[$dia][$key] ?? 0 }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-white">
                                <span class="mt-1 block text-[11px] text-slate-500">un.</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="mt-5 rounded border border-white/10 bg-[#0A0F1A] p-4">
    <p class="text-sm font-semibold text-white">Produtos entregues apenas uma vez por mes</p>
    <p class="mt-1 text-xs text-slate-500">Marque produtos como frutos secos ou padaria quando estes devem aparecer no mapa mensal so na primeira entrega elegivel do mes.</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([...$outrosProdutos, ...$padaria] as $key => $label)
            <label class="flex items-center gap-2 rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-slate-200">
                <input name="produtos_mensais[]" type="checkbox" value="{{ $key }}" @checked(in_array($key, $produtosMensais, true)) class="rounded border-white/10 bg-[#0A0F1A]">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Separadores principais da ficha (Empresa / Faturacao / Entregas).
        const formTabs = Array.from(document.querySelectorAll('[data-form-tab]'));
        const formPanels = Array.from(document.querySelectorAll('[data-form-panel]'));

        const activateFormTab = (nome) => {
            formTabs.forEach((tab) => {
                const isActive = tab.dataset.formTab === nome;

                tab.className = isActive
                    ? 'rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]'
                    : 'rounded px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/5';
                tab.blur();
            });

            formPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.formPanel !== nome);
            });

            try {
                localStorage.setItem('corporateFormTab', nome);
            } catch (e) { /* ignora */ }
        };

        formTabs.forEach((tab) => tab.addEventListener('click', () => activateFormTab(tab.dataset.formTab)));

        // Se houver erros de validacao, abre o separador do primeiro campo com erro.
        const campoComErro = document.querySelector('.border-red-500, [aria-invalid="true"]');
        let separadorInicial = 'empresa';

        try {
            separadorInicial = localStorage.getItem('corporateFormTab') || 'empresa';
        } catch (e) { /* ignora */ }

        if (campoComErro) {
            separadorInicial = campoComErro.closest('[data-form-panel]')?.dataset.formPanel || separadorInicial;
        }

        activateFormTab(formPanels.some((p) => p.dataset.formPanel === separadorInicial) ? separadorInicial : 'empresa');

        const tabs = Array.from(document.querySelectorAll('[data-product-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-product-panel]'));
        const cabazToggle = document.querySelector('[data-cabaz-toggle]');
        const cabazFields = document.querySelector('[data-cabaz-fields]');
        const cabazSelect = cabazFields?.querySelector('select[name="cabaz_tipo"]');

        const activateTab = (activeTab) => {
            tabs.forEach((tab) => {
                const isActive = tab.dataset.productTab === activeTab;

                tab.classList.toggle('bg-[#3B82F6]', isActive);
                tab.classList.toggle('text-white', isActive);
                tab.classList.toggle('text-slate-300', !isActive);
            });

            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.productPanel !== activeTab);
            });
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => activateTab(tab.dataset.productTab));
        });

        const toggleCabazFields = () => {
            if (!cabazToggle || !cabazFields) {
                return;
            }

            cabazFields.classList.toggle('hidden', !cabazToggle.checked);

            if (!cabazToggle.checked && cabazSelect) {
                cabazSelect.value = '';
            }
        };

        cabazToggle?.addEventListener('change', toggleCabazFields);
        toggleCabazFields();
        activateTab('fruta');
    });
</script>
