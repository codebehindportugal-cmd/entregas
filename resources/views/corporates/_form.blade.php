@php
    $dias = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'];
    $frutas = ['banana' => 'Banana', 'maca' => 'Maca', 'pera' => 'Pera', 'laranja' => 'Laranja', 'kiwi' => 'Kiwi', 'uvas' => 'Uvas', 'fruta_epoca' => 'Fruta epoca'];
    $outrosProdutos = ['frutos_secos' => 'Frutos secos', 'mirtilos' => 'Mirtilos', 'framboesas' => 'Framboesas', 'amoras' => 'Amoras', 'morangos' => 'Morangos'];
    $produtosKg = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];
    $diasSelecionados = old('dias_entrega', $corporate->dias_entrega ?? []);
    $frutasPorDiaValores = old('frutas_por_dia', $corporate->frutas_por_dia ?? []);
@endphp
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
    <label class="text-sm text-slate-300">Email faturacao
        <input name="fatura_email" type="email" value="{{ old('fatura_email', $corporate->fatura_email) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Nome faturacao
        <input name="fatura_nome" value="{{ old('fatura_nome', $corporate->fatura_nome) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">NIF
        <input name="fatura_nif" value="{{ old('fatura_nif', $corporate->fatura_nif) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300 lg:col-span-2">Morada faturacao
        <input name="fatura_morada" value="{{ old('fatura_morada', $corporate->fatura_morada) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Numero caixas
        <input name="numero_caixas" type="number" min="0" value="{{ old('numero_caixas', $corporate->numero_caixas ?? 1) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
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
            </div>
        @endforeach
    </div>
</div>
<label class="mt-5 block text-sm text-slate-300">Notas
    <textarea name="notas" rows="4" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('notas', $corporate->notas) }}</textarea>
</label>
<label class="mt-4 flex items-center gap-2 text-sm text-slate-300">
    <input name="ativo" value="1" type="checkbox" @checked(old('ativo', $corporate->ativo ?? true))> Ativo
</label>

<script>
    document.addEventListener('DOMContentLoaded', () => {
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
