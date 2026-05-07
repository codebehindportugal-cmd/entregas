<div class="grid gap-4 md:grid-cols-2">
    <label class="text-sm text-slate-300">Fornecedor
        <input name="fornecedor" required value="{{ old('fornecedor', $tabelaPreco->fornecedor ?: 'Sentido da Fruta') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Descricao
        <input name="descricao" value="{{ old('descricao', $tabelaPreco->descricao) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Valida de
        <input name="valida_de" type="date" required value="{{ old('valida_de', optional($tabelaPreco->valida_de)->toDateString() ?: now()->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Valida ate
        <input name="valida_ate" type="date" value="{{ old('valida_ate', optional($tabelaPreco->valida_ate)->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
</div>
<label class="mt-4 flex items-center gap-2 text-sm text-slate-300">
    <input name="ativa" value="1" type="checkbox" @checked(old('ativa', $tabelaPreco->ativa ?? true))> Ativa
</label>
