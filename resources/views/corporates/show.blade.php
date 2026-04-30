<x-layouts.app title="{{ $corporate->empresa }}">
    <x-page-title title="{{ $corporate->empresa }}" subtitle="{{ $corporate->sucursal }}">
        <a href="{{ route('corporates.edit', $corporate) }}" class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Editar empresa</a>
    </x-page-title>

    <div class="rounded border border-white/10 bg-[#151E2D] p-5 text-sm text-slate-300">
        <p>Responsavel: {{ $corporate->responsavel_nome ?: 'Sem dados' }}</p>
        <p>Telefone: {{ $corporate->responsavel_telefone ?: 'Sem dados' }}</p>
        <p>Dias: {{ implode(', ', $corporate->dias_entrega ?? []) }}</p>
        <p>Total de pecas por semana: {{ $corporate->totalPecasPorSemana() }}</p>
        <div class="mt-3">
            <p class="font-semibold text-white">Pecas por dia de entrega</p>
            @forelse($corporate->pecasPorDiaEntrega() as $diaEntrega => $pecasDia)
                <p>{{ $diaEntrega }}: {{ $pecasDia }} pecas</p>
            @empty
                <p>Sem dias configurados.</p>
            @endforelse
        </div>
        <p>Periodicidade: {{ $corporate->periodicidade_entrega === 'quinzenal' ? 'De 15 em 15 dias' : 'Semanal' }}</p>
        @if($corporate->quinzenal_referencia)
            <p>Referencia quinzenal: {{ $corporate->quinzenal_referencia->format('d/m/Y') }}</p>
        @endif
    </div>

    <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
        <form method="post" action="{{ route('corporates.historico.store', $corporate) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
            @csrf
            <h2 class="text-lg font-semibold text-white">Adicionar historico</h2>
            <label class="mt-4 block text-sm text-slate-300">Data
                <input name="data" type="date" value="{{ old('data', now()->toDateString()) }}" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="mt-4 block text-sm text-slate-300">Texto
                <textarea name="texto" rows="5" required placeholder="Ex: Alterado numero de caixas, contacto atualizado, combinada entrega quinzenal..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('texto') }}</textarea>
            </label>
            <button class="mt-4 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar historico</button>
        </form>

        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <h2 class="text-lg font-semibold text-white">Historico de alteracoes</h2>
            <div class="mt-4 space-y-3">
                @forelse($corporate->historicos as $historico)
                    <article class="rounded border border-white/10 bg-[#0A0F1A] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white">{{ $historico->data->format('d/m/Y') }}</p>
                                <p class="text-xs text-slate-500">
                                    Registado por {{ $historico->user?->name ?? 'Sistema' }} em {{ $historico->created_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <form method="post" action="{{ route('corporates.historico.destroy', [$corporate, $historico]) }}">
                                @csrf
                                @method('delete')
                                <button class="rounded bg-red-500/15 px-3 py-2 text-xs font-semibold text-red-200 hover:bg-red-500/25">Remover</button>
                            </form>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-300">{{ $historico->texto }}</p>
                    </article>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-4 text-sm text-slate-400">Ainda nao existem alteracoes registadas para esta empresa.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
