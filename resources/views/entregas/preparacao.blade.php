@php
    $labels = [
        'banana' => 'Bananas',
        'maca' => 'Macas',
        'pera' => 'Peras',
        'laranja' => 'Laranjas',
        'kiwi' => 'Kiwis',
        'uvas' => 'Uvas',
        'fruta_epoca' => 'Fruta epoca',
        'frutos_secos' => 'Frutos secos',
        'mirtilos' => 'Mirtilos',
        'framboesas' => 'Framboesas',
        'amoras' => 'Amoras',
        'morangos' => 'Morangos',
    ];
    $produtosKg = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];
@endphp

<x-layouts.app title="Preparacao">
    <x-page-title title="Preparacao" subtitle="Quantidades para {{ $dia }} - {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}" />

    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 lg:grid-cols-[1fr_1fr_2fr_auto]">
        <label class="text-sm text-slate-300">Data
            <input name="data" type="date" value="{{ $data }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Dia
            <select name="dia" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                @foreach($dias as $diaOption)
                    <option value="{{ $diaOption }}" @selected($dia === $diaOption)>{{ $diaOption }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">Pesquisar empresa
            <input name="q" value="{{ $q }}" placeholder="Empresa, sucursal ou morada..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver preparacao</button>
            <a href="{{ route('preparacao.index', ['data' => $data, 'dia' => $dia]) }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded border border-white/10 bg-[#151E2D] p-5">
            <p class="text-sm text-slate-400">Clientes</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $corporates->count() + $b2cOrders->count() }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $corporates->count() }} corporate + {{ $b2cOrders->count() }} B2C</p>
        </div>
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Caixas</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $totalCaixas }}</p>
        </div>
        <div class="rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 p-5">
            <p class="text-sm text-blue-200">Pecas totais</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $totalPecas }}</p>
        </div>
        <div class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-5">
            <p class="text-sm text-amber-200">Dia</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $dia }}</p>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded border border-emerald-400/30 bg-emerald-500/10 p-5">
            <p class="text-sm text-emerald-200">Preparado</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $totalFeitos }}</p>
        </div>
        <div class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-5">
            <p class="text-sm text-amber-200">Por fazer</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ $totalPorFazer }}</p>
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach($labels as $key => $label)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <p class="text-sm text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $totaisFrutas[$key] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Empresa</th>
                    <th class="p-3">Caixas</th>
                    @foreach($labels as $label)
                        <th class="p-3">{{ $label }}</th>
                    @endforeach
                    <th class="p-3">Total</th>
                    <th class="p-3">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($corporates as $corporate)
                    @php
                        $frutasEmpresa = $corporate->frutasParaDia($dia);
                        $totalEmpresa = collect(array_keys($labels))->reject(fn (string $key) => in_array($key, $produtosKg, true))->sum(fn (string $key) => (int) ($frutasEmpresa[$key] ?? 0));
                        $item = $preparacaoItems->get('corporate-'.$corporate->id);
                    @endphp
                    <tr class="border-t border-white/10">
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $corporate->empresa }}</p>
                            <p class="text-xs text-slate-400">{{ $corporate->sucursal ?: $corporate->moradaParaEntrega() }}</p>
                        </td>
                        <td class="p-3 font-semibold text-emerald-200">{{ $corporate->numero_caixas }}</td>
                        @foreach(array_keys($labels) as $key)
                            <td class="p-3 text-slate-300">{{ in_array($key, $produtosKg, true) ? number_format((float) ($frutasEmpresa[$key] ?? 0), 2, ',', ' ').' kg' : (int) ($frutasEmpresa[$key] ?? 0) }}</td>
                        @endforeach
                        <td class="p-3 font-semibold text-white">{{ $totalEmpresa }}</td>
                        <td class="p-3">
                            @if($item?->feito)
                                <div class="mb-2 text-xs text-emerald-200">Feito {{ $item->feito_at?->format('H:i') }}</div>
                                <form method="post" action="{{ route('preparacao.update', $item) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="feito" value="0">
                                    <button class="rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200">Marcar por fazer</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('preparacao.update', $item) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="feito" value="1">
                                    <button class="rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">Marcar feito</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($labels) + 4 }}" class="p-4 text-slate-400">Nao existem empresas com entrega neste dia.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <div class="border-b border-white/10 p-4">
            <h2 class="text-lg font-semibold text-white">Clientes B2C</h2>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Tipo</th>
                    <th class="p-3">Produtos</th>
                    <th class="p-3">Preferencias</th>
                    <th class="p-3">Produtos no cabaz</th>
                </tr>
            </thead>
            <tbody>
                @forelse($b2cOrders as $order)
                    @php
                        $item = $preparacaoItems->get('b2c-'.$order->id);
                    @endphp
                    <tr class="border-t border-white/10 align-top">
                        <td class="p-3">
                            <a href="{{ route('encomendas.show', $order) }}" class="font-semibold text-white hover:text-[#22C55E]">#{{ $order->woo_id }} {{ $order->billing_name ?: 'Sem nome' }}</a>
                            <p class="text-xs text-slate-400">{{ $order->billing_phone ?: $order->billing_email }}</p>
                        </td>
                        <td class="p-3 text-slate-300">{{ $order->source_type === 'subscription' ? 'Subscricao' : 'Em processamento' }}</td>
                        <td class="p-3 text-slate-300">
                            @forelse($order->line_items ?? [] as $produto)
                                <p>{{ $produto['quantity'] ?? 0 }}x {{ $produto['name'] ?? 'Produto' }}</p>
                            @empty
                                <span class="text-slate-500">Sem produtos</span>
                            @endforelse
                        </td>
                        <td class="p-3 text-slate-300">
                            @if($order->preferences_text)
                                <span class="whitespace-pre-line">{{ $order->preferences_text }}</span>
                            @else
                                {{ $order->excluded_products ? implode(', ', $order->excluded_products) : 'Sem exclusoes' }}
                            @endif
                        </td>
                        <td class="p-3">
                            <form method="post" action="{{ route('preparacao.produtos.update', $item) }}" class="min-w-64 space-y-2">
                                @csrf
                                @method('put')
                                @forelse($order->line_items ?? [] as $index => $produto)
                                    @php($produtoKey = (string) $index)
                                    <label class="flex items-start gap-2 rounded border border-white/10 bg-[#0A0F1A] p-2 text-xs text-slate-200">
                                        <input name="produtos_picados[]" type="checkbox" value="{{ $produtoKey }}" @checked(in_array($produtoKey, $item->produtos_picados ?? [], true)) class="mt-0.5 rounded border-white/10 bg-[#0A0F1A]">
                                        <span>{{ $produto['quantity'] ?? 0 }}x {{ $produto['name'] ?? 'Produto' }}</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-500">Sem produtos para picar.</p>
                                @endforelse
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <button class="rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">Guardar</button>
                                    @if($item?->feito)
                                        <span class="text-xs text-emerald-200">Feito {{ $item->feito_at?->format('H:i') }}</span>
                                    @else
                                        <span class="text-xs text-amber-200">{{ count($item->produtos_picados ?? []) }}/{{ count($order->line_items ?? []) }} picados</span>
                                    @endif
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-slate-400">Nao existem encomendas B2C para esta preparacao.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
