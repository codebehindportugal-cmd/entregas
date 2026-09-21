<x-layouts.app title="Entregas">
    <x-page-title title="Entregas" subtitle="Atribuicoes por dia da semana" />
    <form method="get" class="mb-5 flex flex-wrap gap-2">
        <input type="hidden" name="q" value="{{ $q }}">
        <input type="hidden" name="user_id" value="{{ $userId }}">
        @foreach($dias as $diaOption)
            <button name="dia" value="{{ $diaOption }}" class="rounded px-4 py-2 text-sm {{ $dia === $diaOption ? 'bg-[#3B82F6] text-white' : 'bg-white/10 text-slate-300' }}">{{ $diaOption }}</button>
        @endforeach
    </form>
    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 lg:grid-cols-[2fr_1fr_auto]">
        <input type="hidden" name="dia" value="{{ $dia }}">
        <label class="text-sm text-slate-300">Pesquisar
            <input name="q" value="{{ $q }}" placeholder="Empresa, cliente B2C, telefone..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Colaborador
            <select name="user_id" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="0">Todos</option>
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->id }}" @selected($userId === $colaborador->id)>{{ $colaborador->name }}</option>
                @endforeach 
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('entregas.index', ['dia' => $dia]) }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>
    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Atribuir em massa</h2>
        <form method="post" action="{{ route('entregas.atribuicoes.bulk') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="dia_semana" value="{{ $dia }}">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
                <select name="user_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($colaboradores as $colaborador)
                        <option value="{{ $colaborador->id }}">{{ $colaborador->name }}</option>
                    @endforeach
                </select>
                <button @disabled($corporates->isEmpty() && $b2cOrders->isEmpty()) class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A] disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300">Atribuir selecionadas</button>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresas</p>
            <div class="grid max-h-96 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($corporates as $corporate)
                    @php($atribuida = $atribuicoes->firstWhere('corporate_id', $corporate->id))
                    <label class="flex gap-3 rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm">
                        <input name="corporate_ids[]" type="checkbox" value="{{ $corporate->id }}" class="mt-1 rounded border-white/10 bg-[#0A0F1A]">
                        <span>
                            <span class="block font-semibold text-white">{{ $corporate->empresa }}</span>
                            <span class="block text-xs text-slate-400">{{ $corporate->sucursal ?: $corporate->moradaParaEntrega() }}</span>
                            @if($atribuida)
                                <span class="mt-2 inline-block rounded bg-[#3B82F6]/15 px-2 py-1 text-xs text-blue-200">Atual: {{ $atribuida->user->name }}</span>
                            @else
                                <span class="mt-2 inline-block rounded bg-[#F59E0B]/15 px-2 py-1 text-xs text-amber-200">Sem colaborador</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-4 text-slate-400">Sem empresas com entrega neste dia.</p>
                @endforelse
            </div>
            <p class="pt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Clientes B2C para {{ \Illuminate\Support\Carbon::parse($dataB2c)->format('d/m/Y') }}</p>
            <div class="grid max-h-96 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($b2cOrders as $order)
                    @php($atribuida = $atribuicoes->firstWhere('woo_order_id', $order->id))
                    <label class="flex gap-3 rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm">
                        <input name="woo_order_ids[]" type="checkbox" value="{{ $order->id }}" class="mt-1 rounded border-white/10 bg-[#0A0F1A]">
                        <span>
                            <span class="block font-semibold text-white">#{{ $order->woo_id }} {{ $order->billing_name ?: 'Sem nome' }}</span>
                            <span class="block text-xs text-slate-400">{{ $order->billing_phone ?: $order->billing_email }}</span>
                            @if($atribuida)
                                <span class="mt-2 inline-block rounded bg-[#3B82F6]/15 px-2 py-1 text-xs text-blue-200">Atual: {{ $atribuida->user->name }}</span>
                            @else
                                <span class="mt-2 inline-block rounded bg-[#F59E0B]/15 px-2 py-1 text-xs text-amber-200">Sem colaborador</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="rounded border border-white/10 bg-[#0A0F1A] p-4 text-slate-400">Sem encomendas B2C para este dia.</p>
                @endforelse
            </div>
        </form>
    </div>

    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Atribuicao individual corporate</h2>
        <form method="post" action="{{ route('entregas.atribuicoes.store') }}" class="grid gap-4 lg:grid-cols-4">
            @csrf
            <input type="hidden" name="tipo" value="corporate">
            <input type="hidden" name="dia_semana" value="{{ $dia }}">
            <select name="corporate_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                @forelse($corporates as $corporate)
                    <option value="{{ $corporate->id }}">{{ $corporate->empresa }}{{ $corporate->sucursal ? ' - '.$corporate->sucursal : '' }}</option>
                @empty
                    <option value="">Sem empresas com entrega neste dia</option>
                @endforelse
            </select>
            <select name="user_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->id }}">{{ $colaborador->name }}</option>
                @endforeach
            </select>
            <button @disabled($corporates->isEmpty()) class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300">Atribuir uma</button>
        </form>
    </div>
    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Atribuicao individual B2C</h2>
        <form method="post" action="{{ route('entregas.atribuicoes.store') }}" class="grid gap-4 lg:grid-cols-4">
            @csrf
            <input type="hidden" name="tipo" value="b2c">
            <input type="hidden" name="dia_semana" value="{{ $dia }}">
            <select name="woo_order_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                @forelse($b2cOrders as $order)
                    <option value="{{ $order->id }}">#{{ $order->woo_id }} {{ $order->billing_name ?: 'Sem nome' }}</option>
                @empty
                    <option value="">Sem encomendas B2C neste dia</option>
                @endforelse
            </select>
            <select name="user_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->id }}">{{ $colaborador->name }}</option>
                @endforeach
            </select>
            <button @disabled($b2cOrders->isEmpty()) class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300">Atribuir B2C</button>
        </form>
    </div>
    @php($podeOrdenar = blank($q))
    <div class="grid gap-6">
        @forelse($atribuicoes->groupBy('user_id') as $rotaUserId => $rota)
            <section class="rounded border border-white/10 bg-[#151E2D] p-5" data-rota>
                <form id="ordem-rota-{{ $rotaUserId }}" method="post" action="{{ route('entregas.ordem.update') }}">
                    @csrf
                    @method('put')
                    <input type="hidden" name="dia_semana" value="{{ $dia }}">
                    <input type="hidden" name="user_id" value="{{ $rotaUserId }}">
                </form>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Rota de {{ $rota->first()->user?->name ?? 'Sem colaborador' }} &middot; {{ $dia }}</h2>
                        <p class="text-sm text-slate-400">{{ $rota->count() }} {{ $rota->count() === 1 ? 'entrega' : 'entregas' }}.
                            @if($podeOrdenar)
                                Arraste pela pega ou use as setas para pôr pela ordem da volta e grave.
                            @else
                                Limpe a pesquisa para poder ordenar a rota.
                            @endif
                        </p>
                    </div>
                    @if($podeOrdenar)
                        <div class="flex items-center gap-3">
                            <span class="hidden rounded bg-[#F59E0B]/15 px-2 py-1 text-xs text-amber-200" data-rota-alterada>Por guardar</span>
                            <button form="ordem-rota-{{ $rotaUserId }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Guardar ordem</button>
                        </div>
                    @endif
                </div>
                <div class="grid gap-2" data-rota-lista>
                    @foreach($rota as $atribuicao)
                        <div class="rounded border border-white/10 bg-[#0A0F1A] p-3" data-rota-item>
                            @if($podeOrdenar)
                                <input type="hidden" form="ordem-rota-{{ $rotaUserId }}" name="ordens[{{ $atribuicao->id }}]" value="{{ $loop->iteration }}" data-rota-ordem>
                            @endif
                            <div class="grid gap-3 lg:grid-cols-[auto_1fr_2fr_auto] lg:items-center">
                                <div class="flex items-center gap-2">
                                    @if($podeOrdenar)
                                        <span class="cursor-grab select-none rounded bg-white/10 px-2 py-1 text-slate-300" title="Arrastar" data-rota-pega>&#8942;&#8942;</span>
                                    @endif
                                    <span class="inline-flex h-8 min-w-8 items-center justify-center rounded bg-[#3B82F6] px-2 text-sm font-semibold text-white" data-rota-numero>{{ $loop->iteration }}</span>
                                    @if($podeOrdenar)
                                        <button type="button" class="rounded bg-white/10 px-2 py-1 text-sm text-slate-200" title="Subir" data-rota-subir>&uarr;</button>
                                        <button type="button" class="rounded bg-white/10 px-2 py-1 text-sm text-slate-200" title="Descer" data-rota-descer>&darr;</button>
                                    @endif
                                </div>
                                <div>
                                    @if($atribuicao->tipo === 'b2c')
                                        <p class="font-semibold text-white">#{{ $atribuicao->wooOrder->woo_id }} {{ $atribuicao->wooOrder->billing_name ?: 'Sem nome' }}</p>
                                        <p class="text-sm text-slate-400">B2C - {{ $atribuicao->wooOrder->billing_phone ?: $atribuicao->wooOrder->billing_email }}</p>
                                    @else
                                        <p class="font-semibold text-white">{{ $atribuicao->corporate->empresa }}{{ $atribuicao->corporate->sucursal ? ' - '.$atribuicao->corporate->sucursal : '' }}</p>
                                        <p class="text-sm text-slate-400">{{ $atribuicao->corporate->moradaParaEntrega() ?: 'Morada por definir' }}{{ $atribuicao->corporate->horario_entrega ? ' · '.$atribuicao->corporate->horario_entrega : '' }}</p>
                                    @endif
                                </div>
                                <form method="post" action="{{ route('entregas.atribuicoes.update', $atribuicao) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="tipo" value="{{ $atribuicao->tipo }}">
                                    <input type="hidden" name="corporate_id" value="{{ $atribuicao->corporate_id }}">
                                    <input type="hidden" name="woo_order_id" value="{{ $atribuicao->woo_order_id }}">
                                    <input type="hidden" name="dia_semana" value="{{ $dia }}">
                                    <select name="user_id" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                        @foreach($colaboradores as $colaborador)
                                            <option value="{{ $colaborador->id }}" @selected($atribuicao->user_id === $colaborador->id)>{{ $colaborador->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Atualizar</button>
                                </form>
                                <form method="post" action="{{ route('entregas.atribuicoes.destroy', $atribuicao) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="w-full rounded bg-red-500/15 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/25">Remover</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded border border-white/10 bg-[#151E2D] p-4 text-slate-400">Sem atribuicoes para este dia.</p>
        @endforelse
    </div>

    <script>
        (() => {
            document.querySelectorAll('[data-rota]').forEach((rota) => {
                const lista = rota.querySelector('[data-rota-lista]');
                const aviso = rota.querySelector('[data-rota-alterada]');
                if (! lista || ! rota.querySelector('[data-rota-pega]')) return;

                const renumerar = () => {
                    lista.querySelectorAll('[data-rota-item]').forEach((item, i) => {
                        item.querySelector('[data-rota-numero]').textContent = i + 1;
                        item.querySelector('[data-rota-ordem]').value = i + 1;
                    });
                    aviso?.classList.remove('hidden');
                };

                let arrastado = null;

                lista.querySelectorAll('[data-rota-item]').forEach((item) => {
                    const pega = item.querySelector('[data-rota-pega]');
                    // So a pega inicia o arrastar, para os selects/botoes continuarem a funcionar.
                    pega.addEventListener('pointerdown', () => { item.draggable = true; });
                    pega.addEventListener('pointerup', () => { if (! arrastado) item.draggable = false; });
                    item.addEventListener('dragstart', (e) => {
                        arrastado = item;
                        e.dataTransfer.effectAllowed = 'move';
                        item.style.opacity = '0.5';
                    });
                    item.addEventListener('dragend', () => {
                        item.draggable = false;
                        item.style.opacity = '';
                        if (arrastado) renumerar();
                        arrastado = null;
                    });

                    item.querySelector('[data-rota-subir]').addEventListener('click', () => {
                        const anterior = item.previousElementSibling;
                        if (anterior) { lista.insertBefore(item, anterior); renumerar(); }
                    });
                    item.querySelector('[data-rota-descer]').addEventListener('click', () => {
                        const seguinte = item.nextElementSibling;
                        if (seguinte) { lista.insertBefore(seguinte, item); renumerar(); }
                    });
                });

                lista.addEventListener('dragover', (e) => {
                    if (! arrastado) return;
                    e.preventDefault();
                    const alvo = [...lista.querySelectorAll('[data-rota-item]')]
                        .filter((el) => el !== arrastado)
                        .find((el) => {
                            const r = el.getBoundingClientRect();
                            return e.clientY < r.top + r.height / 2;
                        });
                    lista.insertBefore(arrastado, alvo ?? null);
                });
            });
        })();
    </script>
</x-layouts.app>
