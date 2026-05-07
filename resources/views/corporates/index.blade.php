<x-layouts.app title="Empresas">
    <x-page-title title="Empresas" subtitle="Gestao de clientes corporate">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('corporates.export') }}" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Exportar</a>
            <form method="post" action="{{ route('corporates.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                @csrf
                <input name="ficheiro" type="file" accept=".json,application/json" required class="max-w-56 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-xs text-slate-200 file:mr-3 file:rounded file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-200">
                <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Importar</button>
            </form>
            <a href="{{ route('corporates.create') }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Nova empresa</a>
        </div>
    </x-page-title>
    @error('ficheiro')
        <div class="mb-6 rounded border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ $message }}</div>
    @enderror
    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
        <label class="text-sm text-slate-300">Pesquisar
            <input name="q" value="{{ $q }}" placeholder="Empresa, morada, telefone..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Dia
            <select name="dia" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                @foreach($dias as $diaOption)
                    <option value="{{ $diaOption }}" @selected($dia === $diaOption)>{{ $diaOption }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">Estado
            <select name="ativo" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="1" @selected($ativo === '1')>Ativas</option>
                <option value="0" @selected($ativo === '0')>Inativas</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Periodicidade
            <select name="periodicidade_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todas</option>
                <option value="semanal" @selected($periodicidade === 'semanal')>Semanal</option>
                <option value="quinzenal" @selected($periodicidade === 'quinzenal')>15 em 15 dias</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('corporates.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>
    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400"><tr><th class="p-3">Empresa</th><th class="p-3">Dias</th><th class="p-3">Pecas por dia</th><th class="p-3">Pecas/semana</th><th class="p-3">Periodicidade</th><th class="p-3">Caixas</th><th class="p-3"></th></tr></thead>
            <tbody>
                @forelse($corporates as $corporate)
                    <tr class="border-t border-white/10">
                        <td class="p-3 text-white">
                            {{ $corporate->empresa }} <span class="text-slate-400">{{ $corporate->sucursal }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $corporate->moradaParaEntrega() ?: 'Morada por definir' }}</span>
                        </td>
                        <td class="p-3">{{ implode(', ', $corporate->dias_entrega ?? []) }}</td>
                        <td class="p-3 text-slate-300">
                            @foreach($corporate->pecasPorDiaEntrega() as $diaEntrega => $pecasDia)
                                <p><span class="text-white">{{ $diaEntrega }}</span>: {{ $pecasDia }}</p>
                            @endforeach
                        </td>
                        <td class="p-3 font-semibold text-white">{{ $corporate->totalPecasPorSemana() }}</td>
                        <td class="p-3">{{ $corporate->periodicidade_entrega === 'quinzenal' ? '15 em 15 dias' : 'Semanal' }}</td>
                        <td class="p-3">{{ $corporate->numero_caixas }}</td>
                        <td class="p-3 text-right">
                            <div class="flex flex-wrap justify-end gap-3">
                                <a class="text-[#3B82F6]" href="{{ route('corporates.show', $corporate) }}">Abrir</a>
                                <a class="text-[#3B82F6]" href="{{ route('corporates.edit', $corporate) }}">Editar</a>
                                <form method="post" action="{{ route('corporates.destroy', $corporate) }}" onsubmit="return confirm('Tem a certeza que quer remover esta empresa?');">
                                    @csrf
                                    @method('delete')
                                    <button class="text-red-300 hover:text-red-200" type="submit">Remover</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-slate-400">Sem empresas para os filtros escolhidos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $corporates->links() }}</div>
</x-layouts.app>
