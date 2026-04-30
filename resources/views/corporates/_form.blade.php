@php
    $dias = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'];
    $frutas = ['banana' => 'Banana', 'maca' => 'Maca', 'pera' => 'Pera', 'laranja' => 'Laranja', 'kiwi' => 'Kiwi', 'uvas' => 'Uvas', 'fruta_epoca' => 'Fruta epoca'];
    $diasSelecionados = old('dias_entrega', $corporate->dias_entrega ?? []);
    $frutasValores = old('frutas', $corporate->frutas ?? []);
    $frutasPorDiaValores = old('frutas_por_dia', $corporate->frutas_por_dia ?? []);
@endphp
<div class="grid gap-4 lg:grid-cols-2">
    <label class="text-sm text-slate-300">Empresa
        <input name="empresa" required value="{{ old('empresa', $corporate->empresa) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Sucursal
        <input name="sucursal" value="{{ old('sucursal', $corporate->sucursal) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
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
    <label class="text-sm text-slate-300">Total de pecas por semana
        <input name="peso_total" type="number" step="1" min="0" value="{{ old('peso_total', $corporate->peso_total ?? 0) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        <span class="mt-1 block text-xs text-slate-500">Este campo corresponde ao antigo "peso total" da importacao.</span>
    </label>
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
<div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-base-fruits>
    @foreach($frutas as $key => $label)
        <label class="text-sm text-slate-300">{{ $label }}
            <input name="frutas[{{ $key }}]" data-fruit-base="{{ $key }}" type="number" min="0" step="{{ $key === 'uvas' ? '0.01' : '1' }}" value="{{ $frutasValores[$key] ?? 0 }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            @if($key === 'uvas')
                <span class="mt-1 block text-xs text-slate-500">Valor em kg.</span>
            @endif
        </label>
    @endforeach
</div>
<div class="mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-300">Pecas por dia da semana</p>
            <p class="mt-1 text-xs text-slate-500">Ao selecionar um dia, os valores base sao copiados para esse dia se ainda estiver a zero.</p>
        </div>
        <button type="button" data-copy-base-to-selected class="rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/15">Copiar para dias selecionados</button>
    </div>
    <div class="mt-3 space-y-4">
        @foreach($dias as $dia)
            <div class="rounded border border-white/10 bg-[#0A0F1A] p-4" data-day-panel="{{ $dia }}">
                <p class="mb-3 text-sm font-semibold text-white">{{ $dia }}</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    @foreach($frutas as $key => $label)
                        <label class="text-xs text-slate-400">{{ $label }}
                            <input name="frutas_por_dia[{{ $dia }}][{{ $key }}]" data-fruit-day="{{ $key }}" type="number" min="0" step="{{ $key === 'uvas' ? '0.01' : '1' }}" value="{{ $frutasPorDiaValores[$dia][$key] ?? 0 }}" class="mt-1 w-full rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-white">
                            @if($key === 'uvas')
                                <span class="mt-1 block text-[11px] text-slate-500">kg</span>
                            @endif
                        </label>
                    @endforeach
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
        const fruitKeys = @json(array_keys($frutas));
        const baseInputs = Object.fromEntries(
            fruitKeys.map((key) => [key, document.querySelector(`[data-fruit-base="${key}"]`)])
        );

        const selectedDayCheckboxes = () => Array.from(document.querySelectorAll('input[name="dias_entrega[]"]:checked'));

        const dayInputs = (day) => Object.fromEntries(
            fruitKeys.map((key) => [key, document.querySelector(`[data-day-panel="${day}"] [data-fruit-day="${key}"]`)])
        );

        const hasDayValues = (inputs) => fruitKeys.some((key) => Number.parseFloat(inputs[key]?.value || '0') > 0);

        const copyBaseToDay = (day, overwrite = false) => {
            const inputs = dayInputs(day);

            if (!overwrite && hasDayValues(inputs)) {
                return;
            }

            fruitKeys.forEach((key) => {
                if (inputs[key] && baseInputs[key]) {
                    inputs[key].value = baseInputs[key].value || '0';
                }
            });
        };

        document.querySelectorAll('input[name="dias_entrega[]"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    copyBaseToDay(checkbox.value);
                }
            });
        });

        document.querySelector('[data-copy-base-to-selected]')?.addEventListener('click', () => {
            selectedDayCheckboxes().forEach((checkbox) => copyBaseToDay(checkbox.value, true));
        });
    });
</script>
