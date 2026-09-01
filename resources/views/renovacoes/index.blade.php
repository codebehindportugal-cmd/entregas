<x-layouts.app title="Renovacoes">
    <x-page-title title="Renovacoes" subtitle="Subscricoes que chegaram ao fim do ciclo de entregas" />

    @if(session('status'))
        <div class="mb-5 rounded border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
    @endif

    @error('renovacao')
        <div class="mb-5 rounded border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ $message }}</div>
    @enderror

    <div class="space-y-8">
        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Por enviar ao cliente ({{ $porEnviar->count() }})</h2>
            <p class="mb-3 text-sm text-slate-400">A encomenda de renovacao ja esta criada no WooCommerce, a espera de pagamento. Falta mandar o link ao cliente.</p>

            @forelse($porEnviar as $order)
                @php($nova = $order->renovacaoWooOrder())
                <div class="mb-3 rounded border border-white/10 bg-[#151E2D] p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm text-slate-300">
                            <a href="{{ route('encomendas.show', $order) }}" class="text-base font-semibold text-white">{{ $order->billing_name ?: 'Sem nome' }}</a>
                            <p class="mt-1">Subscricao #{{ $order->woo_id }} &middot; {{ $order->ciclo_entrega === 'quinzenal' ? '15 em 15 dias' : 'Semanal' }} &middot; {{ $order->dia_entrega ? ucfirst($order->dia_entrega) : 'sem dia' }}</p>
                            <p>Ultima entrega do ciclo: {{ \Illuminate\Support\Carbon::parse($order->ultimaEntregaDoCiclo())->format('d/m/Y') }}</p>
                            @if($nova)
                                <p>Encomenda nova: <a href="{{ route('encomendas.show', $nova) }}" class="text-blue-200">#{{ $nova->woo_id }}</a>
                                    @if(! $nova->paymentUrl())
                                        <span class="text-amber-200">(sem link de pagamento)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($nova && $nova->whatsappPagamentoUrl())
                                <a href="{{ $nova->whatsappPagamentoUrl() }}" target="_blank" rel="noopener" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Enviar por WhatsApp</a>
                            @else
                                <span class="rounded bg-white/10 px-4 py-2 text-sm text-slate-300">Sem telefone ou sem link</span>
                            @endif
                            <form method="post" action="{{ route('renovacoes.enviada', $order) }}">
                                @csrf
                                @method('put')
                                <button class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Ja enviei</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="rounded border border-white/10 bg-[#151E2D] p-5 text-sm text-slate-400">Nada por enviar.</p>
            @endforelse
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-white">Ciclo terminado, sem renovacao ({{ $porRenovar->count() }})</h2>
            <p class="mb-3 text-sm text-slate-400">Fizeram as entregas todas e nao estao marcadas como auto-renovaveis (ou a renovacao automatica falhou).</p>

            @forelse($porRenovar as $order)
                <div class="mb-3 rounded border border-white/10 bg-[#151E2D] p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm text-slate-300">
                            <a href="{{ route('encomendas.show', $order) }}" class="text-base font-semibold text-white">{{ $order->billing_name ?: 'Sem nome' }}</a>
                            <p class="mt-1">Subscricao #{{ $order->woo_id }} &middot; {{ $order->ciclo_entrega === 'quinzenal' ? '15 em 15 dias' : 'Semanal' }} &middot; {{ $order->dia_entrega ? ucfirst($order->dia_entrega) : 'sem dia' }}</p>
                            <p>Ultima entrega do ciclo: {{ \Illuminate\Support\Carbon::parse($order->ultimaEntregaDoCiclo())->format('d/m/Y') }}</p>
                            <p class="{{ $order->renovacao_automatica ? 'text-emerald-200' : 'text-slate-500' }}">{{ $order->renovacao_automatica ? 'Auto-renovavel' : 'Sem renovacao automatica' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($order->whatsappRenovacaoUrl())
                                <a href="{{ $order->whatsappRenovacaoUrl() }}" target="_blank" rel="noopener" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Perguntar por WhatsApp</a>
                            @endif
                            <form method="post" action="{{ route('renovacoes.store', $order) }}" onsubmit="return confirm('Criar a encomenda de renovacao no WooCommerce, em pagamento pendente?');">
                                @csrf
                                <button class="rounded bg-[#3B82F6]/20 px-4 py-2 text-sm font-semibold text-blue-200 hover:bg-[#3B82F6]/30">Criar renovacao</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="rounded border border-white/10 bg-[#151E2D] p-5 text-sm text-slate-400">Nada por renovar.</p>
            @endforelse
        </section>

        @if($enviadas->isNotEmpty())
            <section>
                <h2 class="mb-3 text-lg font-semibold text-white">Ja enviadas ({{ $enviadas->count() }})</h2>
                <div class="rounded border border-white/10 bg-[#151E2D] p-5 text-sm text-slate-300">
                    @foreach($enviadas as $order)
                        <p class="border-t border-white/10 py-2 first:border-0 first:pt-0">
                            <a href="{{ route('encomendas.show', $order) }}" class="font-semibold text-white">{{ $order->billing_name ?: 'Sem nome' }}</a>
                            <span class="text-slate-500">&middot; enviada {{ $order->renovacao_enviada_em->format('d/m/Y') }}</span>
                        </p>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
