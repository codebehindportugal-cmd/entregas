<x-layouts.app title="Pedido recebido">
    <x-page-title :title="'Pedido #'.$pedido->id.' — '.($pedido->nomeCliente() ?: $pedido->remetente ?: 'sem nome')"
                  :subtitle="($pedido->canal === 'whatsapp' ? 'WhatsApp' : ucfirst($pedido->canal)).' · recebido '.($pedido->recebido_em?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '?').' · '.$pedido->etiquetaEstado()">
        <a href="{{ route('pedidos-recebidos.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Voltar à caixa</a>
    </x-page-title>

    @if(session('status'))
        <div class="mb-5 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    @php($resumo = $pedido->resumo)

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- O que o cliente escreveu --}}
        <section class="rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-1 text-lg font-semibold text-[#14532d]">Mensagem do cliente</h2>
            <p class="mb-3 text-xs text-slate-500">De: {{ $pedido->remetente ?: '?' }}@if($pedido->assunto) · Assunto: {{ $pedido->assunto }}@endif</p>
            <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap rounded bg-slate-50 p-3 font-sans text-sm text-slate-800">{{ $pedido->texto_original }}</pre>
        </section>

        {{-- O que ficou interpretado --}}
        <section class="space-y-4">
            @if($pedido->estado === 'criado' && $pedido->wooOrder)
                <div class="rounded border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                    <p class="font-semibold">Encomenda #{{ $pedido->wooOrder->woo_id }} criada{{ $pedido->tratadoPor ? ' por '.$pedido->tratadoPor->name : '' }}{{ $pedido->tratado_em ? ' em '.$pedido->tratado_em->timezone(config('app.timezone'))->format('d/m H:i') : '' }}.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('encomendas.show', $pedido->wooOrder) }}" class="rounded bg-white px-3 py-2 font-semibold text-blue-800 shadow-sm">Abrir encomenda</a>
                        @if($pedido->wooOrder->whatsappPagamentoUrl())
                            <a href="{{ $pedido->wooOrder->whatsappPagamentoUrl() }}" target="_blank" rel="noopener" class="rounded bg-[#22C55E] px-3 py-2 font-semibold text-[#0A0F1A]">Enviar link de pagamento por WhatsApp</a>
                        @endif
                        @if($pedido->wooOrder->paymentUrl())
                            <button type="button" onclick="navigator.clipboard.writeText(@js($pedido->wooOrder->paymentUrl())); this.textContent='Copiado'" class="rounded bg-white px-3 py-2 font-semibold text-slate-700 shadow-sm">Copiar link de pagamento</button>
                        @endif
                    </div>
                </div>
            @endif

            @if(! empty($pedido->duvidas))
                <div class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="mb-1 font-semibold">Dúvidas a confirmar com o cliente</p>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach($pedido->duvidas as $duvida)
                            <li>{{ $duvida }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(! empty($pedido->erros))
                <div class="rounded border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                    <p class="mb-1 font-semibold">Por resolver antes de confirmar</p>
                    <ul class="space-y-2">
                        @foreach($pedido->erros as $erro)
                            <li>
                                @if(! empty($erro['linha']))<span class="font-semibold">Linha {{ $erro['linha'] }}:</span>@endif
                                {{ $erro['mensagem'] ?? $erro['codigo'] ?? '' }}
                                @if(! empty($erro['sugestoes']))
                                    <span class="block text-xs text-red-700">Opções:
                                        {{ collect($erro['sugestoes'])->map(fn ($s) => is_array($s) ? (($s['nome'] ?? $s['descricao'] ?? json_encode($s, JSON_UNESCAPED_UNICODE)).(isset($s['id']) ? ' (id '.$s['id'].')' : '')) : $s)->implode(' · ') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($pedido->estado === 'falta_telefone')
                <form method="post" action="{{ route('pedidos-recebidos.update', $pedido) }}" class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                    @csrf
                    @method('put')
                    <label class="block text-sm font-medium text-slate-700">Telefone do cliente
                        <input name="telefone" required placeholder="912 345 678" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 text-slate-950 shadow-sm">
                    </label>
                    <button class="mt-3 rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Pôr telefone e validar</button>
                </form>
            @endif

            @if(is_array($resumo))
                <div class="rounded border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-lg font-semibold text-[#14532d]">Encomenda interpretada</h2>
                    @php($cliente = $resumo['cliente'] ?? [])
                    <dl class="mb-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt class="text-slate-500">Cliente</dt>
                        <dd class="text-slate-900">{{ $cliente['nome'] ?? '—' }}
                            <span class="text-xs text-slate-500">{{ ($cliente['encomendas_anteriores'] ?? 0) > 0 ? '· '.$cliente['encomendas_anteriores'].' encomendas anteriores' : '· cliente novo' }}</span>
                        </dd>
                        <dt class="text-slate-500">Telefone</dt><dd>{{ $cliente['telefone'] ?? '—' }}</dd>
                        <dt class="text-slate-500">Email</dt><dd>{{ $cliente['email'] ?? '—' }}</dd>
                        <dt class="text-slate-500">Morada</dt><dd>{{ collect([$cliente['morada'] ?? null, $cliente['codigo_postal'] ?? null, $cliente['cidade'] ?? null])->filter()->implode(', ') ?: '—' }}</dd>
                        <dt class="text-slate-500">Entrega</dt><dd>{{ ucfirst($resumo['dia_entrega'] ?? '—') }}{{ ! empty($resumo['data_entrega']) ? ' · '.\Illuminate\Support\Carbon::parse($resumo['data_entrega'])->format('d/m/Y') : '' }}</dd>
                        @if(! empty($resumo['notas']))<dt class="text-slate-500">Notas</dt><dd>{{ $resumo['notas'] }}</dd>@endif
                        @if(! empty($resumo['cupoes']))<dt class="text-slate-500">Cupões</dt><dd>{{ collect($resumo['cupoes'])->map(fn ($c) => is_array($c) ? ($c['codigo'] ?? json_encode($c)) : $c)->implode(', ') }}</dd>@endif
                    </dl>

                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr><th class="py-1">Pedido</th><th class="py-1">Fica</th><th class="py-1 text-right">Subtotal</th></tr>
                        </thead>
                        <tbody>
                            @foreach($resumo['linhas'] ?? [] as $linha)
                                <tr class="border-t border-slate-100 align-top">
                                    <td class="py-2 text-slate-600">{{ rtrim(rtrim(number_format((float) ($linha['quantidade_pedida'] ?? 0), 3, ',', ''), '0'), ',') }} {{ $linha['unidade_pedida'] ?? '' }} {{ $linha['texto_original'] ?? '' }}</td>
                                    <td class="py-2 text-slate-900">
                                        @if(! empty($linha['produto']))
                                            {{ $linha['equivalencia'] ?? ($linha['quantidade_woo'].' x '.$linha['produto']['nome']) }}
                                        @else
                                            <span class="text-red-700">por resolver</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right">{{ isset($linha['subtotal']) ? number_format((float) $linha['subtotal'], 2, ',', ' ').' €' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-slate-200 font-semibold">
                                <td colspan="2" class="py-2">Total estimado</td>
                                <td class="py-2 text-right">{{ number_format((float) ($resumo['total_estimado'] ?? 0), 2, ',', ' ') }} €</td>
                            </tr>
                        </tfoot>
                    </table>

                    @if(! empty($pedido->avisos))
                        <ul class="mt-4 space-y-1 text-xs text-slate-600">
                            @foreach($pedido->avisos as $aviso)
                                <li>⚠ @if(! empty($aviso['linha']))Linha {{ $aviso['linha'] }}: @endif{{ $aviso['mensagem'] ?? $aviso['codigo'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @elseif($pedido->estado === 'novo')
                <div class="rounded border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                    Ainda não foi interpretado. O Claude trata dele no fim do dia, ou podes preencher o pedido em baixo.
                </div>
            @endif

            @if($pedido->aberto())
                <div class="flex flex-wrap gap-2">
                    @if(empty($pedido->erros) && is_array($resumo))
                        <form method="post" action="{{ route('pedidos-recebidos.confirmar', $pedido) }}"
                              onsubmit="return confirm(@js(empty($pedido->duvidas) ? 'Criar a encomenda no WooCommerce, em pagamento pendente?' : 'Ainda há dúvidas por confirmar com o cliente. Criar a encomenda mesmo assim?'));">
                            @csrf
                            <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Confirmar e criar encomenda</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('pedidos-recebidos.descartar', $pedido) }}" class="flex gap-2"
                          onsubmit="return confirm('Descartar este pedido?');">
                        @csrf
                        <input name="motivo" placeholder="Motivo (opcional)" class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-950">
                        <button class="rounded bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">Descartar</button>
                    </form>
                </div>
            @elseif($pedido->estado === 'descartado')
                <div class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
                    Descartado{{ $pedido->notas ? ': '.$pedido->notas : '' }}.
                    <form method="post" action="{{ route('pedidos-recebidos.reabrir', $pedido) }}" class="mt-2">
                        @csrf
                        <button class="rounded bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">Reabrir</button>
                    </form>
                </div>
            @endif
        </section>
    </div>

    @if($pedido->aberto())
        <details class="mt-6 rounded border border-slate-200 bg-white p-5 shadow-sm" @if($errors->has('pedido_json') || $pedido->estado === 'novo') open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Corrigir o pedido (formato da API)</summary>
            <p class="mt-2 text-xs text-slate-500">
                Mesmo formato do <code>/api/v1/encomendas/validar</code>. Num cliente que já existe basta o telefone.
                Unidades: <code>kg</code>, <code>g</code>, <code>un</code>, <code>emb</code> ou <code>null</code> se o cliente não disse.
                Para escolher entre produtos parecidos, põe <code>"woo_product_id"</code> na linha com o id das opções.
                Dia de entrega: <code>segunda</code>, <code>quarta</code> ou <code>sabado</code>.
            </p>
            <form method="post" action="{{ route('pedidos-recebidos.update', $pedido) }}" class="mt-3">
                @csrf
                @method('put')
                <textarea name="pedido_json" rows="18" class="w-full rounded border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-900">{{ old('pedido_json', $pedidoJson) }}</textarea>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Guardar e validar</button>
                    @if(! empty($pedido->duvidas))
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="hidden" name="limpar_duvidas" value="0">
                            <input type="checkbox" name="limpar_duvidas" value="1" class="h-4 w-4 rounded border-slate-300"> As dúvidas já estão esclarecidas
                        </label>
                    @endif
                </div>
            </form>
        </details>
    @endif
</x-layouts.app>
