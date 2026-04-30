<x-layouts.app title="Painel">
    <x-page-title title="Painel operacional" subtitle="Resumo do trabalho de hoje, encomendas ativas e preparacao" />

    <section class="mb-6 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
        <div class="rounded border border-white/10 bg-[#111B17] p-6 shadow-xl shadow-black/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-emerald-200/80">Hoje</p>
                    <h2 class="mt-1 text-3xl font-semibold text-white">{{ now()->format('d/m/Y') }}</h2>
                    <p class="mt-2 text-sm text-slate-400">Entregas, preparacao e clientes B2C num so sitio.</p>
                </div>
                <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-20 w-20 rounded bg-white p-2">
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="text-slate-300">Entregas concluidas</span>
                        <span class="font-semibold text-white">{{ $progressoEntregas }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded bg-white/10">
                        <div class="h-full rounded bg-[#22C55E]" style="width: {{ $progressoEntregas }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="text-slate-300">Preparacao feita</span>
                        <span class="font-semibold text-white">{{ $progressoPreparacao }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded bg-white/10">
                        <div class="h-full rounded bg-[#F59E0B]" style="width: {{ $progressoPreparacao }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('preparacao.index') }}" class="rounded border border-emerald-400/20 bg-emerald-500/10 p-5 hover:bg-emerald-500/15">
                <p class="text-sm text-emerald-200">Preparar cabazes</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $preparacaoPorFazer }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $preparacaoFeita }} feitos de {{ $preparacaoTotal }}</p>
            </a>
            <a href="{{ route('entregas.verificacao') }}" class="rounded border border-blue-400/20 bg-blue-500/10 p-5 hover:bg-blue-500/15">
                <p class="text-sm text-blue-200">Entregas pendentes</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $pendentesHoje }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $entreguesHoje }} entregues de {{ $entregasHoje }}</p>
            </a>
            <a href="{{ route('encomendas.index') }}" class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-5 hover:bg-[#F59E0B]/15">
                <p class="text-sm text-amber-200">Clientes B2C ativos</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $b2cAtivas }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $subscricoesAtivas }} subscricoes</p>
            </a>
            <a href="{{ route('corporates.index') }}" class="rounded border border-white/10 bg-white/5 p-5 hover:bg-white/10">
                <p class="text-sm text-slate-300">Empresas ativas</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $corporatesAtivos }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $colaboradoresAtivos }} colaboradores ativos</p>
            </a>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Por preparar</h2>
                <a href="{{ route('preparacao.index') }}" class="text-sm text-[#22C55E]">Abrir</a>
            </div>
            <div class="space-y-3">
                @forelse($proximasPreparacoes as $item)
                    <div class="rounded border border-white/10 bg-[#0A0F1A] p-3">
                        <p class="font-semibold text-white">{{ $item->tipo === 'corporate' ? $item->corporate?->empresa : '#'.$item->wooOrder?->woo_id.' '.$item->wooOrder?->billing_name }}</p>
                        <p class="text-xs text-slate-400">{{ $item->tipo === 'corporate' ? 'Corporate' : 'Cliente B2C' }}</p>
                    </div>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm text-slate-400">Tudo preparado para hoje.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Entregas por fechar</h2>
                <a href="{{ route('entregas.verificacao') }}" class="text-sm text-[#22C55E]">Ver estado</a>
            </div>
            <div class="space-y-3">
                @forelse($entregasPendentes as $entrega)
                    <div class="rounded border border-white/10 bg-[#0A0F1A] p-3">
                        <p class="font-semibold text-white">{{ $entrega->corporate?->empresa }}</p>
                        <p class="text-xs text-slate-400">{{ $entrega->user?->name ?: 'Sem colaborador' }}</p>
                    </div>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm text-slate-400">Sem entregas pendentes.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Ultimos B2C</h2>
                <a href="{{ route('encomendas.index') }}" class="text-sm text-[#22C55E]">Clientes B2C</a>
            </div>
            <div class="space-y-3">
                @forelse($ultimasEncomendas as $order)
                    <div class="rounded border border-white/10 bg-[#0A0F1A] p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white">#{{ $order->woo_id }} {{ $order->billing_name ?: 'Sem nome' }}</p>
                                <p class="text-xs text-slate-400">{{ $order->status === 'subscricao' ? 'Subscricao' : 'Em processamento' }}</p>
                            </div>
                            <span class="text-sm font-semibold text-white">{{ number_format((float) $order->total, 2, ',', ' ') }} €</span>
                        </div>
                    </div>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm text-slate-400">Sem encomendas B2C sincronizadas.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
