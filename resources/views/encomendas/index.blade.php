<x-layouts.app title="Encomendas B2C">
    @php
        $sortUrl = fn (string $column) => route('encomendas.index', array_merge(request()->query(), [
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ]));
        $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
        $periodUrl = fn (string $value) => route('encomendas.index', array_merge(request()->query(), ['periodo' => $value, 'page' => null]));
    @endphp
    <x-page-title title="Encomendas" subtitle="Cache local das encomendas WooCommerce">
        <div class="flex flex-wrap gap-2">
            <form method="post" action="{{ route('encomendas.sync') }}">
                @csrf
                <button class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Sincronizar agora</button>
            </form>
            <form method="post" action="{{ route('encomendas.destroy-all') }}" onsubmit="return confirm('Tem a certeza que quer remover TODAS as encomendas da cache local? Depois pode sincronizar novamente a partir do WooCommerce.');">
                @csrf
                @method('delete')
                <button class="rounded bg-red-500/15 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/25">Eliminar todas</button>
            </form>
        </div>
    </x-page-title>

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach(['' => 'Todas', 'adiadas' => 'Adiadas', 'preferencias' => 'Preferencias'] as $value => $label)
            <a href="{{ route('encomendas.index', ['tipo' => $value, 'q' => $q, 'status' => $status, 'dia_entrega' => $diaEntrega, 'source_type' => $sourceType]) }}" class="rounded px-4 py-2 text-sm {{ $tipo === $value ? 'bg-[#3B82F6] text-white' : 'bg-white/10 text-slate-300' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach(['' => 'Sempre', 'dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mes', 'personalizado' => 'Personalizado'] as $value => $label)
                <a href="{{ $periodUrl($value) }}" class="rounded px-3 py-2 text-sm font-medium {{ $periodo === $value ? 'bg-[#3B82F6] text-white' : 'bg-white/10 text-slate-300' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="grid gap-3 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_1fr_auto]">
        <label class="text-sm text-slate-300">Pesquisar
            <input name="q" value="{{ $q }}" placeholder="Cliente, telefone, email ou ID..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Inicio
            <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Fim
            <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Estado
            <select name="status" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="em_processamento" @selected($status === 'em_processamento')>Em processamento</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Dia entrega
            <select name="dia_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="segunda" @selected($diaEntrega === 'segunda')>Segunda</option>
                <option value="quarta" @selected($diaEntrega === 'quarta')>Quarta</option>
                <option value="sabado" @selected($diaEntrega === 'sabado')>Sabado</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Tipo
            <select name="source_type" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="order" @selected($sourceType === 'order')>Encomenda</option>
                <option value="subscription" @selected($sourceType === 'subscription')>Subscricao</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('encomendas.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
        </div>
    </form>

    <div class="overflow-hidden rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3"><a href="{{ $sortUrl('id') }}">Encomenda{{ $sortMark('id') }}</a></th>
                    <th class="p-3"><a href="{{ $sortUrl('cliente') }}">Cliente{{ $sortMark('cliente') }}</a></th>
                    <th class="p-3">Produtos</th>
                    <th class="p-3">Dia</th>
                    <th class="p-3">Entregas</th>
                    <th class="p-3">Preferencias</th>
                    <th class="p-3"><a href="{{ $sortUrl('total') }}">Total{{ $sortMark('total') }}</a></th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr class="border-t border-white/10 align-top">
                        <td class="p-3">
                            @php($isSubscricao = $order->source_type === 'subscription' || $order->status === 'subscricao')
                            @php($entregasSubscricao = $isSubscricao ? $order->entregasSubscricao() : null)
                            <p class="font-semibold text-white">#{{ $order->woo_id }}</p>
                            <p class="text-xs text-slate-400">{{ $isSubscricao ? 'Subscricao' : 'Em processamento' }}</p>
                            <p class="mt-1 inline-block rounded bg-white/10 px-2 py-1 text-xs text-slate-200">{{ $isSubscricao ? 'Subscricao' : 'Encomenda' }}</p>
                            @if($isSubscricao)
                                <p class="mt-1 inline-block rounded bg-[#3B82F6]/15 px-2 py-1 text-xs text-blue-200">{{ $order->ciclo_entrega === 'quinzenal' ? '15 em 15 dias' : 'Semanal' }}</p>
                            @endif
                            @if($order->postponed_until)
                                <p class="mt-1 rounded bg-[#F59E0B]/15 px-2 py-1 text-xs text-amber-200">Adiada ate {{ $order->postponed_until->format('d/m/Y') }}</p>
                            @endif
                            @if($isSubscricao && $entregasSubscricao['proxima'])
                                <p class="mt-1 rounded bg-[#3B82F6]/15 px-2 py-1 text-xs text-blue-200">Prox. entrega {{ \Illuminate\Support\Carbon::parse($entregasSubscricao['proxima'])->format('d/m/Y') }}</p>
                            @elseif(! $isSubscricao && $order->next_payment_at)
                                <p class="mt-1 rounded bg-[#3B82F6]/15 px-2 py-1 text-xs text-blue-200">Prox. encomenda {{ $order->next_payment_at->format('d/m/Y') }}</p>
                            @endif
                            @if($order->first_delivery_at)
                                <p class="mt-1 rounded bg-emerald-500/15 px-2 py-1 text-xs text-emerald-200">1a entrega {{ $order->first_delivery_at->format('d/m/Y') }}</p>
                            @endif
                            @if($order->source_type === 'subscription' && $order->fimCicloSubscricao())
                                <p class="mt-1 rounded bg-white/10 px-2 py-1 text-xs text-slate-200">Fim {{ $order->fimCicloSubscricao()->format('d/m/Y') }}</p>
                            @elseif($order->subscription_ends_at)
                                <p class="mt-1 rounded bg-white/10 px-2 py-1 text-xs text-slate-200">Fim {{ $order->subscription_ends_at->format('d/m/Y') }}</p>
                            @endif
                            @if($order->scheduled_delivery_at)
                                <p class="mt-1 rounded bg-emerald-500/15 px-2 py-1 text-xs text-emerald-200">Entrega {{ $order->scheduled_delivery_at->format('d/m/Y') }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $order->billing_name ?: 'Sem nome' }}</p>
                            <p class="text-xs text-slate-400">{{ $order->billing_phone ?: 'Sem telefone' }}</p>
                            <p class="text-xs text-slate-400">{{ $order->billing_email }}</p>
                            <a class="mt-2 inline-block text-xs font-semibold text-[#3B82F6]" href="{{ route('encomendas.show', $order) }}">Abrir perfil</a>
                        </td>
                        <td class="p-3 text-slate-300">
                            @forelse($order->line_items ?? [] as $item)
                                <p>{{ $item['quantity'] ?? 0 }}x {{ $item['name'] ?? 'Produto' }}</p>
                            @empty
                                <p class="text-slate-500">Sem produtos</p>
                            @endforelse
                        </td>
                        <td class="p-3 text-slate-300">{{ $order->dia_entrega ? ucfirst($order->dia_entrega) : '-' }}</td>
                        <td class="p-3 text-slate-300">
                            @if($isSubscricao)
                                @php($entregas = $entregasSubscricao)
                                <div class="space-y-1">
                                    <p><span class="text-white">{{ $entregas['total'] }}</span> no ciclo</p>
                                    <p><span class="text-emerald-200">{{ $entregas['feitas'] }}</span> feitas</p>
                                    <p><span class="text-amber-200">{{ $entregas['por_realizar'] }}</span> por realizar</p>
                                    @if($entregas['proxima'])
                                        <p class="text-xs text-slate-400">Proxima {{ \Illuminate\Support\Carbon::parse($entregas['proxima'])->format('d/m/Y') }}</p>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-500">-</span>
                            @endif
                        </td>
                        <td class="p-3 text-slate-300">
                            @if($order->preferences_text)
                                <span class="whitespace-pre-line">{{ $order->preferences_text }}</span>
                            @elseif($order->excluded_products)
                                {{ implode(', ', $order->excluded_products) }}
                            @else
                                <span class="text-slate-500">Sem exclusoes</span>
                            @endif
                        </td>
                        <td class="p-3 font-semibold text-white">{{ number_format((float) $order->total, 2, ',', ' ') }} €</td>
                        <td class="p-3 text-right">
                            @if($order->status === 'subscricao' && $order->whatsappRenovacaoUrl())
                                <a href="{{ $order->whatsappRenovacaoUrl() }}" target="_blank" rel="noopener" class="mb-2 inline-block rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">WhatsApp</a>
                            @endif
                            @if($order->status === 'pending' && $order->whatsappPagamentoUrl())
                                <a href="{{ $order->whatsappPagamentoUrl() }}" target="_blank" rel="noopener" class="mb-2 inline-block rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">Enviar pagamento</a>
                            @endif
                            <a href="{{ route('encomendas.show', $order) }}" class="mb-2 inline-block rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/15">Perfil</a>
                            <form method="post" action="{{ route('encomendas.postpone', $order) }}" class="mb-2 grid gap-2">
                                @csrf
                                @method('put')
                                <input name="postponed_until" type="date" value="{{ optional($order->postponed_until)->toDateString() }}" class="w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-2 text-xs text-white">
                                <button class="rounded bg-[#F59E0B]/20 px-3 py-2 text-xs font-semibold text-amber-200 hover:bg-[#F59E0B]/30">Adiar</button>
                            </form>
                            @if($order->postponed_until)
                                <form method="post" action="{{ route('encomendas.postpone.clear', $order) }}" class="mb-2">
                                    @csrf
                                    @method('delete')
                                    <button class="rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/15">Limpar adiamento</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('encomendas.duplicate', $order) }}" class="mb-2" onsubmit="return confirm('Publicar uma nova encomenda WooCommerce em pagamento pendente com os mesmos dados e produtos?');">
                                @csrf
                                <button class="rounded bg-[#3B82F6]/20 px-3 py-2 text-xs font-semibold text-blue-200 hover:bg-[#3B82F6]/30">Publicar</button>
                            </form>
                            <form method="post" action="{{ route('encomendas.destroy', $order) }}">
                                @csrf
                                @method('delete')
                                <button class="rounded bg-red-500/15 px-3 py-2 text-xs font-semibold text-red-200 hover:bg-red-500/25">Remover</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-4 text-slate-400">Ainda nao existem encomendas sincronizadas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
