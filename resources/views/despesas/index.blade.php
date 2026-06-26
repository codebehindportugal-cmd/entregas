<x-layouts.app title="Despesas e Faturas">
    @php
        $mesAnterior = \Illuminate\Support\Carbon::createFromDate($ano, $mes, 1)->subMonth();
        $mesSeguinte = \Illuminate\Support\Carbon::createFromDate($ano, $mes, 1)->addMonth();
        $baseParams = array_filter(['search' => $search]);
    @endphp

    <x-page-title title="Despesas e Faturas" subtitle="Gestao de custos e faturas — Horta da Maria">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('despesas.pdf', array_merge($baseParams, ['ano' => $ano, 'mes' => $mes])) }}"
               class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">PDF</a>
            <a href="{{ route('despesas.csv', array_merge($baseParams, ['ano' => $ano, 'mes' => $mes])) }}"
               class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">CSV</a>
            <a href="{{ route('despesas.create') }}"
               class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Nova fatura</a>
        </div>
    </x-page-title>

    {{-- Navegacao mes --}}
    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('despesas.index', array_merge($baseParams, ['ano' => $mesAnterior->year, 'mes' => $mesAnterior->month])) }}"
           class="rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-slate-300 hover:bg-white/10">&#8592;</a>
        <span class="text-lg font-semibold text-white">{{ $inicio->translatedFormat('F Y') }}</span>
        <a href="{{ route('despesas.index', array_merge($baseParams, ['ano' => $mesSeguinte->year, 'mes' => $mesSeguinte->month])) }}"
           class="rounded border border-white/10 bg-[#151E2D] px-3 py-2 text-sm text-slate-300 hover:bg-white/10">&#8594;</a>
    </div>

    {{-- Barra de resumo --}}
    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Faturas — {{ $inicio->translatedFormat('F Y') }}</p>
        <div class="flex flex-wrap gap-6">
            <div>
                <p class="text-xs text-slate-400">Total do mes</p>
                <p class="text-xl font-bold text-emerald-400">{{ number_format($resumo['total'], 2, ',', ' ') }} EUR</p>
                <p class="text-xs text-slate-500">{{ $resumo['count'] }} faturas</p>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-xs text-slate-400">Total do mes</p>
            <p class="text-2xl font-bold text-white">{{ number_format($resumo['total'], 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-xs text-slate-400">Numero de faturas</p>
            <p class="text-2xl font-bold text-white">{{ $resumo['count'] }}</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-xs text-slate-400">IVA total (linhas)</p>
            <p class="text-2xl font-bold text-white">{{ number_format($analytics['iva_total'], 2, ',', ' ') }} EUR</p>
            <p class="text-xs text-slate-500">Subtotal s/ IVA: {{ number_format($analytics['subtotal'], 2, ',', ' ') }} EUR</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="mes" value="{{ $mes }}">
        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
            <label class="text-sm text-slate-300">Pesquisar
                <input name="search" value="{{ $search }}" placeholder="Titulo, fornecedor, n. fatura..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white placeholder-slate-500">
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Filtrar</button>
                <a href="{{ route('despesas.index', ['ano' => $ano, 'mes' => $mes]) }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200 hover:bg-white/15">Limpar</a>
            </div>
        </div>
    </form>

    {{-- Tabela Desktop --}}
    <div class="hidden overflow-x-auto rounded border border-white/10 bg-[#151E2D] lg:block">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-xs uppercase tracking-wider text-slate-400">
                <tr>
                    <th class="p-3">Data</th>
                    <th class="p-3">Titulo / Fornecedor</th>
                    <th class="p-3 text-right">Valor</th>
                    <th class="p-3 text-center">Linhas</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($despesas as $despesa)
                    <tr class="cursor-pointer border-t border-white/10 hover:bg-white/5" data-toggle="despesa-{{ $despesa->id }}">
                        <td class="whitespace-nowrap p-3 text-slate-300">{{ $despesa->data->format('d/m/Y') }}</td>
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $despesa->titulo }}</p>
                            @if($despesa->fornecedor)
                                <p class="text-xs text-slate-400">{{ $despesa->fornecedor }}</p>
                            @endif
                            @if($despesa->numero_fatura)
                                <p class="text-xs text-slate-500">N. {{ $despesa->numero_fatura }}</p>
                            @endif
                        </td>
                        <td class="p-3 text-right font-semibold text-white">{{ number_format($despesa->total_fatura, 2, ',', ' ') }} EUR</td>
                        <td class="p-3 text-center">
                            @if($despesa->items->isNotEmpty())
                                <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-300">{{ $despesa->items->count() }}</span>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex flex-wrap justify-end gap-3">
                                @if($despesa->ficheiro_path)
                                    <button type="button" class="text-blue-400 hover:text-blue-300"
                                        onclick="abrirLightbox('{{ Storage::disk('public')->url($despesa->ficheiro_path) }}', '{{ $despesa->titulo }}'); event.stopPropagation()">
                                        Ver ficheiro
                                    </button>
                                @endif
                                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('despesas.edit', $despesa) }}">Editar</a>
                                <form method="post" action="{{ route('despesas.destroy', $despesa) }}" onsubmit="return confirm('Remover esta despesa?')" onclick="event.stopPropagation()">
                                    @csrf
                                    @method('delete')
                                    <button class="text-red-400 hover:text-red-300" type="submit">Remover</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @if($despesa->items->isNotEmpty())
                        <tr id="despesa-{{ $despesa->id }}" class="hidden border-t border-white/5 bg-[#0A0F1A]">
                            <td colspan="5" class="px-6 py-3">
                                <table class="w-full text-xs">
                                    <thead class="text-slate-500">
                                        <tr>
                                            <th class="pb-1 text-left">Descricao</th>
                                            <th class="pb-1 text-right">Qtd compra</th>
                                            <th class="pb-1 text-right">Qtd unidades</th>
                                            <th class="pb-1 text-right">Custo/unid.</th>
                                            <th class="pb-1 text-right">Preco unit.</th>
                                            <th class="pb-1 text-right">IVA</th>
                                            <th class="pb-1 text-right">Total s/ IVA</th>
                                            <th class="pb-1 text-right">Total c/ IVA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($despesa->items as $item)
                                            <tr class="border-t border-white/5">
                                                <td class="py-1 text-slate-300">{{ $item->descricao }}</td>
                                                <td class="py-1 text-right text-slate-400">{{ number_format((float) $item->quantidade, 3, ',', '') }} {{ $item->unidade_compra ?? 'un' }}</td>
                                                <td class="py-1 text-right text-slate-400">{{ number_format((float) $item->quantidade_unidades, 3, ',', '') }} un</td>
                                                <td class="py-1 text-right font-semibold text-emerald-300">{{ $item->custo_unitario !== null ? number_format($item->custo_unitario, 4, ',', '').' EUR' : '-' }}</td>
                                                <td class="py-1 text-right text-slate-400">{{ number_format((float) $item->preco_unitario, 4, ',', '') }} EUR</td>
                                                <td class="py-1 text-right text-slate-400">{{ number_format((float) $item->iva_percentagem, 0, ',', '') }}%</td>
                                                <td class="py-1 text-right text-slate-300">{{ number_format($item->total_sem_iva, 2, ',', '') }} EUR</td>
                                                <td class="py-1 text-right font-semibold text-white">{{ number_format($item->total_com_iva, 2, ',', '') }} EUR</td>
                                            </tr>
                                        @endforeach
                                        <tr class="border-t border-white/10">
                                            <td colspan="7" class="py-1 text-right text-xs text-slate-500">Subtotal s/ IVA: {{ number_format($despesa->subtotal_calculado, 2, ',', '') }} EUR &nbsp; IVA: {{ number_format($despesa->iva_calculado, 2, ',', '') }} EUR</td>
                                            <td class="py-1 text-right font-bold text-emerald-400">{{ number_format($despesa->total_fatura, 2, ',', '') }} EUR</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-400">Sem despesas para este periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Cards Mobile --}}
    <div class="space-y-3 lg:hidden">
        @forelse($despesas as $despesa)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-white">{{ $despesa->titulo }}</p>
                        @if($despesa->fornecedor)
                            <p class="text-xs text-slate-400">{{ $despesa->fornecedor }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-500">{{ $despesa->data->format('d/m/Y') }}</p>
                    </div>
                    <p class="shrink-0 text-base font-bold text-white">{{ number_format($despesa->total_fatura, 2, ',', ' ') }} EUR</p>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @if($despesa->items->isNotEmpty())
                        <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-semibold text-emerald-300">{{ $despesa->items->count() }} linhas</span>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    @if($despesa->ficheiro_path)
                        <button type="button" class="text-blue-400" onclick="abrirLightbox('{{ Storage::disk('public')->url($despesa->ficheiro_path) }}', '{{ $despesa->titulo }}')">Ver ficheiro</button>
                    @endif
                    <a class="text-emerald-400" href="{{ route('despesas.edit', $despesa) }}">Editar</a>
                    <form method="post" action="{{ route('despesas.destroy', $despesa) }}" onsubmit="return confirm('Remover esta despesa?')">
                        @csrf @method('delete')
                        <button class="text-red-400" type="submit">Remover</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded border border-white/10 bg-[#151E2D] p-6 text-center text-slate-400">Sem despesas para este periodo.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $despesas->links() }}</div>

    {{-- Lightbox para ficheiros --}}
    <div id="lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4" onclick="fecharLightbox()">
        <div class="relative max-h-[90vh] max-w-4xl overflow-auto" onclick="event.stopPropagation()">
            <button class="absolute right-2 top-2 rounded bg-black/50 px-3 py-1 text-white" onclick="fecharLightbox()">X</button>
            <img id="lightbox-img" src="" alt="" class="max-h-[85vh] max-w-full rounded">
            <iframe id="lightbox-pdf" src="" class="hidden h-[85vh] w-[80vw] rounded border-0"></iframe>
        </div>
    </div>

    <script>
        // Toggle linhas inline
        document.querySelectorAll('[data-toggle]').forEach(function(row) {
            row.addEventListener('click', function() {
                var targetId = this.dataset.toggle;
                var target = document.getElementById(targetId);
                if (target) target.classList.toggle('hidden');
            });
        });

        // Lightbox
        function abrirLightbox(url, titulo) {
            var lb = document.getElementById('lightbox');
            var img = document.getElementById('lightbox-img');
            var pdf = document.getElementById('lightbox-pdf');
            lb.classList.remove('hidden');
            lb.classList.add('flex');
            if (url.match(/\.pdf(\?|$)/i)) {
                img.classList.add('hidden');
                pdf.classList.remove('hidden');
                pdf.src = url;
                img.src = '';
            } else {
                pdf.classList.add('hidden');
                img.classList.remove('hidden');
                img.src = url;
                img.alt = titulo;
                pdf.src = '';
            }
        }

        function fecharLightbox() {
            var lb = document.getElementById('lightbox');
            lb.classList.add('hidden');
            lb.classList.remove('flex');
            document.getElementById('lightbox-img').src = '';
            document.getElementById('lightbox-pdf').src = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharLightbox();
        });
    </script>
</x-layouts.app>
