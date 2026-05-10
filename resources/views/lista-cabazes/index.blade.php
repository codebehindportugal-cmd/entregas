<x-layouts.app title="Listas semanais">
    <x-page-title title="Listas semanais" subtitle="Composicao dos cabazes por semana">
        <a href="{{ route('lista-cabazes.create') }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Nova lista</a>
    </x-page-title>

    <form method="post" action="{{ route('lista-cabazes.import') }}" enctype="multipart/form-data" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        @csrf
        <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto]">
            <label class="text-sm text-slate-300">Importar cabazes de subscricao ja feitos
                <input name="ficheiro" type="file" accept=".json,application/json" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-slate-200 file:mr-3 file:rounded file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-200">
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm text-slate-300">
                <input name="publicar" value="1" type="checkbox" class="rounded border-white/10 bg-[#0A0F1A]"> Publicar ao importar
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white">Importar JSON</button>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Atualiza pela combinacao semana, mes e ano. Os produtos dessa semana sao substituidos pelos do ficheiro.</p>
    </form>

    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Semana</th>
                    <th class="p-3">Mes/Ano</th>
                    <th class="p-3">Descricao</th>
                    <th class="p-3">Estado</th>
                    <th class="p-3 text-right">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($listas as $lista)
                    <tr class="border-t border-white/10">
                        <td class="p-3 font-semibold text-white">Semana {{ $lista->semana_numero }}</td>
                        <td class="p-3 text-slate-300">{{ \App\Models\ListaCabaz::meses()[$lista->mes] }} {{ $lista->ano }}</td>
                        <td class="p-3 text-slate-300">{{ $lista->descricao ?: '-' }}</td>
                        <td class="p-3">
                            <span class="rounded px-2 py-1 text-xs font-semibold {{ $lista->estado === 'publicada' ? 'bg-[#22C55E]/15 text-emerald-200' : 'bg-[#F59E0B]/15 text-amber-200' }}">{{ ucfirst($lista->estado) }}</span>
                        </td>
                        <td class="p-3 text-right">
                            <a href="{{ route('lista-cabazes.edit', $lista) }}" class="inline-block rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200">Editar</a>
                            <a href="{{ route('lista-cabazes.totais', $lista) }}" class="inline-block rounded bg-[#3B82F6]/20 px-3 py-2 text-xs font-semibold text-blue-200">Totais</a>
                            <form method="post" action="{{ route('lista-cabazes.destroy', $lista) }}" class="inline-block" onsubmit="return confirm('Apagar esta lista semanal?');">
                                @csrf
                                @method('delete')
                                <button class="rounded bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Apagar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-slate-400">Ainda nao existem listas semanais.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $listas->links() }}</div>
</x-layouts.app>
