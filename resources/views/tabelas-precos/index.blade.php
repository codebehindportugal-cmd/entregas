<x-layouts.app title="Tabelas de precos">
    <x-page-title title="Tabelas de precos" subtitle="Catalogos de fornecedores">
        <a href="{{ route('tabelas-precos.create') }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Nova tabela</a>
    </x-page-title>

    <form method="post" action="{{ route('tabelas-precos.manual') }}" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 md:grid-cols-[1fr_1fr_auto]">
        @csrf
        <label class="text-sm text-slate-300">Outro produtor
            <input name="fornecedor" placeholder="Ex: Produtor local" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Descricao
            <input name="descricao" placeholder="Tabela manual" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <div class="flex items-end">
            <button class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white">Criar manual</button>
        </div>
    </form>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Fornecedor</th>
                    <th class="p-3">Descricao</th>
                    <th class="p-3">Periodo</th>
                    <th class="p-3">Produtos</th>
                    <th class="p-3">Estado</th>
                    <th class="p-3 text-right">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tabelas as $tabela)
                    <tr class="border-t border-white/10">
                        <td class="p-3 font-semibold text-white">{{ $tabela->fornecedor }}</td>
                        <td class="p-3 text-slate-300">{{ $tabela->descricao ?: '-' }}</td>
                        <td class="p-3 text-slate-300">{{ $tabela->valida_de->format('d/m/Y') }} - {{ $tabela->valida_ate?->format('d/m/Y') ?: 'sem fim' }}</td>
                        <td class="p-3 text-slate-300">{{ $tabela->itens_count }}</td>
                        <td class="p-3"><span class="rounded px-2 py-1 text-xs font-semibold {{ $tabela->ativa ? 'bg-[#22C55E]/15 text-emerald-200' : 'bg-white/10 text-slate-300' }}">{{ $tabela->ativa ? 'Ativa' : 'Inativa' }}</span></td>
                        <td class="p-3 text-right">
                            <a href="{{ route('tabelas-precos.show', $tabela) }}" class="inline-block rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200">Ver</a>
                            <a href="{{ route('tabelas-precos.edit', $tabela) }}" class="inline-block rounded bg-[#3B82F6]/20 px-3 py-2 text-xs font-semibold text-blue-200">Editar</a>
                            <form method="post" action="{{ route('tabelas-precos.clonar', $tabela) }}" class="inline-block">
                                @csrf
                                <button class="rounded bg-[#F59E0B]/20 px-3 py-2 text-xs font-semibold text-amber-200">Clonar</button>
                            </form>
                            <form method="post" action="{{ route('tabelas-precos.destroy', $tabela) }}" class="inline-block" onsubmit="return confirm('Eliminar esta tabela e todos os seus produtos?');">
                                @csrf
                                @method('delete')
                                <button class="rounded bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-slate-400">Ainda nao existem tabelas de precos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tabelas->links() }}</div>
</x-layouts.app>
