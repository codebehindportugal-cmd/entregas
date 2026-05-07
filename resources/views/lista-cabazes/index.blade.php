<x-layouts.app title="Listas semanais">
    <x-page-title title="Listas semanais" subtitle="Composicao dos cabazes por semana">
        <a href="{{ route('lista-cabazes.create') }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Nova lista</a>
    </x-page-title>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
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
