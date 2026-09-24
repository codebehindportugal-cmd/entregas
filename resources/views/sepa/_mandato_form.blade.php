@php
    /** @var \App\Models\SepaMandato|null $mandato */
    $m = $mandato ?? null;
    $input = 'mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm';
@endphp

<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <label class="text-sm font-medium text-slate-700">Nome do devedor *
        <input name="nome_devedor" value="{{ old('nome_devedor', $m?->nome_devedor) }}" required maxlength="70" class="{{ $input }}"
               placeholder="Ex.: Laboratoires Dermatologiques d'Uriage Portugal">
        <span class="mt-1 block text-xs text-slate-500">Como aparece no mandato. Acentos são retirados no ficheiro.</span>
    </label>
    <label class="text-sm font-medium text-slate-700">NIF
        <input name="nif" value="{{ old('nif', $m?->nif) }}" maxlength="20" class="{{ $input }}">
    </label>
    <label class="text-sm font-medium text-slate-700">Referência do mandato *
        <input name="mandato_ref" value="{{ old('mandato_ref', $m?->mandato_ref) }}" required maxlength="35" class="{{ $input }}">
        <span class="mt-1 block text-xs text-slate-500">A que está no mandato assinado (nos ficheiros atuais é o NIF).</span>
    </label>
    <label class="text-sm font-medium text-slate-700">Código no nº do pedido
        <input name="codigo_pedido" value="{{ old('codigo_pedido', $m?->codigo_pedido) }}" maxlength="10" class="{{ $input }} font-mono" placeholder="Ex.: 26">
        <span class="mt-1 block text-xs text-slate-500">O nº do pedido fica código + ano + dia + mês da cobrança (26 a 06/10/2026 → 26260610).</span>
    </label>
    <label class="text-sm font-medium text-slate-700">IBAN do cliente *
        <input name="iban" value="{{ old('iban', $m?->iban) }}" required maxlength="40" class="{{ $input }} uppercase font-mono"
               placeholder="PT50 ...">
    </label>
    <label class="text-sm font-medium text-slate-700">BIC do banco do cliente
        <input name="bic" value="{{ old('bic', $m?->bic) }}" maxlength="11" class="{{ $input }} uppercase font-mono" placeholder="Ex.: BESCPTPL">
        <span class="mt-1 block text-xs text-slate-500">Opcional, mas os ficheiros atuais levam-no.</span>
    </label>
    <label class="text-sm font-medium text-slate-700">Data de assinatura do mandato *
        <input name="data_assinatura" type="date" value="{{ old('data_assinatura', $m?->data_assinatura?->format('Y-m-d')) }}" required class="{{ $input }}">
    </label>
    <label class="text-sm font-medium text-slate-700">Valor por mês (€) *
        <input name="valor_mensal" type="number" step="0.01" min="0.01" value="{{ old('valor_mensal', $m?->valor_mensal) }}" required class="{{ $input }}">
    </label>
    <label class="text-sm font-medium text-slate-700">Último mês já cobrado *
        <input name="ultimo_mes_cobrado" type="month" value="{{ old('ultimo_mes_cobrado', $m?->ultimo_mes_cobrado) }}" required class="{{ $input }}">
        <span class="mt-1 block text-xs text-slate-500">A app cobra a partir do mês seguinte. Avança sozinho quando geras um ficheiro.</span>
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-sm font-medium text-slate-700">Máx. meses por cobrança
            <input name="max_meses_por_cobranca" type="number" min="1" max="12" value="{{ old('max_meses_por_cobranca', $m?->max_meses_por_cobranca ?? 2) }}" required class="{{ $input }}">
        </label>
        <label class="text-sm font-medium text-slate-700">Dia habitual
            <input name="dia_cobranca" type="number" min="1" max="28" value="{{ old('dia_cobranca', $m?->dia_cobranca ?? 5) }}" required class="{{ $input }}">
        </label>
    </div>
    <label class="text-sm font-medium text-slate-700 md:col-span-2">Notas
        <input name="notas" value="{{ old('notas', $m?->notas) }}" maxlength="2000" class="{{ $input }}">
    </label>
    <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
        <input type="hidden" name="ativo" value="0">
        <input name="ativo" type="checkbox" value="1" @checked(old('ativo', $m?->ativo ?? true)) class="rounded border-slate-300">
        Ativo (aparece para gerar cobranças)
    </label>
</div>
