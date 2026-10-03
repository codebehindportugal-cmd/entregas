<x-layouts.app title="Caixa de pedidos">
    <x-page-title title="Caixa de pedidos" subtitle="Encomendas que chegaram por email ou WhatsApp, à espera de confirmação">
        <a href="{{ route('definicoes-pedidos.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Definições</a>
    </x-page-title>

    @if(session('status'))
        <div class="mb-5 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @php
        $abertos = collect(\App\Models\PedidoRecebido::ABERTOS)->sum(fn ($e) => $contagens[$e] ?? 0);
        $filtros = ['abertos' => "Por tratar ({$abertos})"]
            + collect(\App\Models\PedidoRecebido::ESTADOS)->mapWithKeys(fn ($label, $e) => [$e => $label.' ('.($contagens[$e] ?? 0).')'])->all()
            + ['todos' => 'Todos'];
        $cores = [
            'novo' => 'bg-slate-100 text-slate-700',
            'pronto' => 'bg-emerald-100 text-emerald-800',
            'com_duvidas' => 'bg-amber-100 text-amber-800',
            'falta_telefone' => 'bg-red-100 text-red-800',
            'criado' => 'bg-blue-100 text-blue-800',
            'descartado' => 'bg-slate-100 text-slate-400',
        ];
    @endphp

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach($filtros as $chave => $label)
            <a href="{{ route('pedidos-recebidos.index', ['estado' => $chave]) }}"
               class="rounded-full px-3 py-1.5 text-sm font-medium {{ $filtro === $chave ? 'bg-[#14532d] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($pedidos as $pedido)
            <a href="{{ route('pedidos-recebidos.show', $pedido) }}" class="block rounded border border-slate-200 bg-white p-4 shadow-sm hover:border-emerald-400">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $cores[$pedido->estado] ?? 'bg-slate-100' }}">{{ $pedido->etiquetaEstado() }}</span>
                            <span class="rounded bg-slate-50 px-2 py-0.5 text-xs text-slate-500">{{ $pedido->canal === 'whatsapp' ? 'WhatsApp' : ucfirst($pedido->canal) }}</span>
                            <span class="font-semibold text-slate-900">{{ $pedido->nomeCliente() ?: $pedido->remetente ?: 'Remetente desconhecido' }}</span>
                        </div>
                        @if($pedido->assunto)
                            <p class="mt-1 text-sm text-slate-600">{{ $pedido->assunto }}</p>
                        @endif
                        <p class="mt-1 truncate text-sm text-slate-500">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $pedido->texto_original), 160) }}</p>
                        @if(! empty($pedido->duvidas) || ! empty($pedido->erros))
                            <p class="mt-1 text-xs text-amber-700">{{ count($pedido->erros ?? []) + count($pedido->duvidas ?? []) }} ponto(s) a ver</p>
                        @endif
                    </div>
                    <div class="text-right text-sm text-slate-500">
                        <p>{{ $pedido->recebido_em?->timezone(config('app.timezone'))->format('d/m H:i') }}</p>
                        @if(isset($pedido->resumo['total_estimado']))
                            <p class="font-semibold text-slate-800">{{ number_format((float) $pedido->resumo['total_estimado'], 2, ',', ' ') }} €</p>
                        @endif
                        @if($pedido->wooOrder)
                            <p class="text-blue-700">#{{ $pedido->wooOrder->woo_id }}</p>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <p class="rounded border border-slate-200 bg-white p-5 text-sm text-slate-500">Nada por aqui.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $pedidos->links() }}</div>
</x-layouts.app>
