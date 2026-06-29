<tr class="border-t border-slate-100">
    <td class="p-2">
        <input name="itens[{{ $idx }}][produto]"
               value="{{ $item['produto'] ?? '' }}"
               required placeholder="Nome do produto"
               class="w-full min-w-[140px] rounded border border-slate-200 px-2 py-1 text-xs text-slate-950">
        <input type="hidden" name="itens[{{ $idx }}][cabaz_tipo]" value="{{ $tipo }}">
    </td>
    <td class="p-2">
        <input name="itens[{{ $idx }}][categoria]"
               value="{{ $item['categoria'] ?? '' }}"
               placeholder="fruta, legume…"
               class="w-full min-w-[80px] rounded border border-slate-200 px-2 py-1 text-xs text-slate-950">
    </td>
    <td class="p-2">
        <input name="itens[{{ $idx }}][quantidade]"
               type="number" step="0.001" min="0.001"
               value="{{ $item['quantidade'] ?? 1 }}" required
               class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950">
    </td>
    <td class="p-2">
        <input name="itens[{{ $idx }}][unidade]"
               value="{{ $item['unidade'] ?? 'un' }}"
               class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950">
    </td>
    <td class="p-2">
        <input name="itens[{{ $idx }}][peso_unitario_kg]"
               type="number" step="0.001" min="0"
               value="{{ $item['peso_unitario_kg'] ?? '' }}"
               class="w-full rounded border border-slate-200 px-2 py-1 text-center text-xs text-slate-950"
               placeholder="—">
    </td>
    <td class="p-2 text-center">
        <button type="button" onclick="removerLinha(this)"
                class="text-base font-bold leading-none text-rose-400 hover:text-rose-600">×</button>
    </td>
</tr>
