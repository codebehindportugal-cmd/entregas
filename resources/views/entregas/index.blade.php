@php
    // A cor de cada zona liga o selo na lista a volta respetiva.
    $cores = $rotas->mapWithKeys(fn ($rota) => [$rota['zona']->id => $rota['zona']->cor ?: '#64748B']);
    $porAtribuir = $entregas->whereNull('zona_id')->count();
    $comSugestao = $entregas->whereNull('zona_id')->whereNotNull('sugestao_id')->count();
    $totalEmpresas = $entregas->where('tipo', 'corporate')->count();
    $totalB2c = $entregas->where('tipo', 'b2c')->count();
@endphp

<x-layouts.app title="Entregas">
    <x-page-title title="Rotas" subtitle="As entregas entram sozinhas na zona do código postal; aqui ordena-se a volta e corrige-se o que for preciso" />

    @if($porConverter > 0)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded border border-amber-400/30 bg-[#F59E0B]/10 p-4 text-sm text-amber-100">
            <span><strong>{{ $porConverter }}</strong> {{ $porConverter === 1 ? 'entrega deste dia ainda está atribuída' : 'entregas deste dia ainda estão atribuídas' }} a colaboradores, de antes das zonas.</span>
            <a href="{{ route('zonas.index') }}#converter" class="rounded bg-[#F59E0B] px-3 py-1.5 font-semibold text-[#0A0F1A]">Converter para zonas</a>
        </div>
    @endif

    {{-- Dias --}}
    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach($dias as $diaOption)
            <a href="{{ route('entregas.index', ['dia' => $diaOption]) }}"
               class="rounded px-4 py-2 text-sm {{ $dia === $diaOption ? 'bg-[#3B82F6] font-semibold text-white' : 'bg-white/10 text-slate-300 hover:bg-white/15' }}">{{ $diaOption }}</a>
        @endforeach
    </nav>

    {{-- Resumo do dia --}}
    <div class="mb-6 flex flex-wrap items-center gap-2 rounded border border-white/10 bg-[#151E2D] p-4 text-sm">
        <span class="mr-2 text-slate-300">
            <strong class="text-white">{{ $entregas->count() }}</strong> entregas para {{ \Illuminate\Support\Carbon::parse($dataDia)->format('d/m') }}
        </span>
        @if($porAtribuir > 0)
            <span class="rounded bg-[#F59E0B]/15 px-2 py-1 font-semibold text-amber-200">{{ $porAtribuir }} por atribuir</span>
        @elseif($entregas->isNotEmpty())
            <span class="rounded bg-[#22C55E]/15 px-2 py-1 font-semibold text-green-200">Tudo atribuído</span>
        @endif
        @if($parceirosLocais->isNotEmpty())
            <span class="rounded bg-white/5 px-2 py-1 text-slate-400" title="{{ $parceirosLocais->implode(', ') }}">+ {{ $parceirosLocais->count() }} por parceiros locais</span>
        @endif
        <span class="mx-1 hidden h-5 w-px bg-white/10 sm:inline-block"></span>
        @foreach($rotas as $rota)
            <a href="#rota-{{ $rota['zona']->id }}" class="inline-flex items-center gap-2 rounded bg-white/5 px-2 py-1 text-slate-200 hover:bg-white/10">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $cores[$rota['zona']->id] }}"></span>
                {{ $rota['zona']->nome }}
                <strong class="text-white">{{ $rota['paragens']->count() }}</strong>
            </a>
        @endforeach
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">

        {{-- ─────────── Entregas do dia ─────────── --}}
        <section class="rounded border border-white/10 bg-[#151E2D] lg:sticky lg:top-4" data-entregas>
            <form id="form-atribuir" method="post" action="{{ route('entregas.atribuicoes.bulk') }}">
                @csrf
                <input type="hidden" name="dia_semana" value="{{ $dia }}">
            </form>

            <div class="space-y-3 border-b border-white/10 p-4">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white">Entregas do dia</h2>
                    <span class="text-xs text-slate-400">ordenadas por código postal</span>
                </div>

                <input type="search" placeholder="Procurar nome, morada, código postal, telefone…" autocomplete="off"
                       class="w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white placeholder:text-slate-500" data-filtro-texto>

                <div class="flex flex-wrap gap-2 text-sm">
                    <div class="inline-flex overflow-hidden rounded border border-white/10" role="group" aria-label="Estado">
                        <button type="button" class="px-3 py-1.5" data-filtro-estado="pendentes">Por atribuir <span class="opacity-70">{{ $porAtribuir }}</span></button>
                        <button type="button" class="border-l border-white/10 px-3 py-1.5" data-filtro-estado="todas">Todas <span class="opacity-70">{{ $entregas->count() }}</span></button>
                    </div>
                    <div class="inline-flex overflow-hidden rounded border border-white/10" role="group" aria-label="Tipo">
                        <button type="button" class="px-3 py-1.5" data-filtro-tipo="">Tudo</button>
                        <button type="button" class="border-l border-white/10 px-3 py-1.5" data-filtro-tipo="corporate">Empresas <span class="opacity-70">{{ $totalEmpresas }}</span></button>
                        <button type="button" class="border-l border-white/10 px-3 py-1.5" data-filtro-tipo="b2c">B2C <span class="opacity-70">{{ $totalB2c }}</span></button>
                    </div>
                </div>

                @if($comSugestao > 0)
                    <form method="post" action="{{ route('entregas.atribuicoes.sugeridas') }}" class="flex flex-wrap items-center justify-between gap-2 rounded border border-[#3B82F6]/30 bg-[#3B82F6]/10 px-3 py-2 text-sm text-blue-100"
                          onsubmit="return confirm('Pôr {{ $comSugestao }} {{ $comSugestao === 1 ? 'entrega' : 'entregas' }} na zona sugerida pelo código postal?')">
                        @csrf
                        <input type="hidden" name="dia_semana" value="{{ $dia }}">
                        <span><strong>{{ $comSugestao }}</strong> sem zona {{ $comSugestao === 1 ? 'tem' : 'têm' }} zona sugerida pelo código postal.</span>
                        <button class="rounded bg-[#3B82F6] px-3 py-1.5 font-semibold text-white">Aplicar sugestões</button>
                    </form>
                @endif

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" class="rounded border-white/10 bg-[#0A0F1A]" data-selecionar-visiveis>
                    Selecionar as que estão à vista
                </label>
            </div>

            <div class="max-h-[60vh] overflow-y-auto p-2 lg:max-h-[calc(100vh-22rem)]" data-lista-entregas>
                @foreach($entregas as $entrega)
                    <label class="flex cursor-pointer gap-3 rounded p-2.5 hover:bg-white/5 has-[:checked]:bg-[#3B82F6]/15"
                           data-entrega
                           data-tipo="{{ $entrega['tipo'] }}"
                           data-atribuida="{{ $entrega['zona_id'] ? '1' : '0' }}"
                           data-busca="{{ mb_strtolower(implode(' ', array_filter([$entrega['nome'], $entrega['morada'], $entrega['cp'], $entrega['localidade'], $entrega['detalhe'], $entrega['zona_nome'], $entrega['antes']]))) }}">
                        <input type="checkbox" form="form-atribuir"
                               name="{{ $entrega['tipo'] === 'corporate' ? 'corporate_ids[]' : 'woo_order_ids[]' }}"
                               value="{{ $entrega['id'] }}"
                               class="mt-1 shrink-0 rounded border-white/10 bg-[#0A0F1A]" data-entrega-check>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-2">
                                <span class="font-semibold text-white">{{ $entrega['nome'] }}</span>
                                @if($entrega['zona_id'])
                                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded bg-white/5 px-2 py-0.5 text-xs text-slate-200">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $cores[$entrega['zona_id']] ?? '#64748B' }}"></span>{{ $entrega['zona_nome'] }}
                                    </span>
                                @else
                                    <span class="flex shrink-0 flex-col items-end gap-1">
                                        <span class="rounded bg-[#F59E0B]/15 px-2 py-0.5 text-xs text-amber-200" @if($entrega['antes']) title="Antes das zonas estava com {{ $entrega['antes'] }}" @endif>Sem zona{{ $entrega['antes'] ? ' · era '.$entrega['antes'] : '' }}</span>
                                        @if($entrega['sugestao_id'])
                                            <span class="inline-flex items-center gap-1 text-xs text-slate-400" title="Sugerida pelo código postal">
                                                <span class="h-2 w-2 rounded-full" style="background: {{ $cores[$entrega['sugestao_id']] ?? '#64748B' }}"></span>{{ $entrega['sugestao_nome'] }}?
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-slate-400">
                                @if($entrega['cp'] || $entrega['localidade'])
                                    <span class="font-semibold text-slate-200">{{ trim($entrega['cp'].' '.$entrega['localidade']) }}</span>
                                    @if($entrega['morada']) · @endif
                                @endif
                                {{ $entrega['morada'] ?: ($entrega['cp'] || $entrega['localidade'] ? '' : 'Morada por definir') }}
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                {{ $entrega['tipo'] === 'corporate' ? 'Empresa' : 'B2C' }}@if($entrega['detalhe']) · {{ $entrega['detalhe'] }}@endif
                            </span>
                        </span>
                    </label>
                @endforeach

                <p class="hidden p-6 text-center text-sm text-slate-400" data-lista-vazia>
                    @if($entregas->isEmpty())
                        Sem entregas neste dia.
                    @else
                        <span data-vazia-pendentes>Está tudo atribuído. 🎉<br><button type="button" class="mt-2 underline" data-filtro-estado="todas">Ver todas</button></span>
                        <span data-vazia-filtro>Nenhuma entrega corresponde à pesquisa.</span>
                    @endif
                </p>
            </div>

            {{-- Barra de atribuir --}}
            <div class="sticky bottom-0 rounded-b border-t border-white/10 bg-[#151E2D] p-3 shadow-[0_-6px_12px_-8px_rgba(0,0,0,0.25)]">
                <p class="text-sm text-slate-400" data-barra-vazia>Marque as entregas e depois carregue na zona.</p>
                <div class="hidden space-y-2" data-barra-ativa>
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="text-white"><strong data-contagem>0</strong> selecionadas — pôr na zona:</span>
                        <button type="button" class="text-slate-400 underline hover:text-slate-200" data-limpar-selecao>Limpar</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($zonas as $zonaBotao)
                            <button form="form-atribuir" name="zona_id" value="{{ $zonaBotao->id }}"
                                    class="inline-flex items-center gap-2 rounded bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $cores[$zonaBotao->id] ?? '#64748B' }}"></span>{{ $zonaBotao->nome }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ─────────── Rotas ─────────── --}}
        <div class="grid gap-4">
            @foreach($rotas as $rota)
                @php($zona = $rota['zona'])
                @php($paragens = $rota['paragens'])
                @php($colaborador = $rota['colaborador'])
                <section id="rota-{{ $zona->id }}" class="scroll-mt-4 rounded border border-white/10 bg-[#151E2D]" style="border-left: 4px solid {{ $cores[$zona->id] }}" data-rota>
                    <form id="ordem-rota-{{ $zona->id }}" method="post" action="{{ route('entregas.ordem.update') }}">
                        @csrf
                        @method('put')
                        <input type="hidden" name="zona_id" value="{{ $zona->id }}">
                    </form>

                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                        <div>
                            <h2 class="text-base font-semibold text-white">{{ $zona->nome }}@unless($zona->ativo) <span class="text-xs font-normal text-slate-400">(desativada)</span>@endunless
                                @if($paragens->isNotEmpty())
                                    <a href="{{ route('mapa-volta', ['zona_id' => $zona->id, 'data' => $dataDia, 'todas' => 1]) }}" class="ml-2 text-xs font-normal text-[#3B82F6] underline">ver no mapa</a>
                                @endif
                            </h2>
                            <p class="text-sm">
                                @if($colaborador)
                                    <span class="text-slate-200">Faz: <strong class="text-white">{{ $colaborador->name }}</strong></span>
                                    @if($rota['substituicao'])
                                        <span class="ml-1 rounded bg-[#F59E0B]/15 px-1.5 py-0.5 text-xs text-amber-200">substituição até {{ $rota['substituicao']->fim->format('d/m') }}</span>
                                    @endif
                                @else
                                    <span class="rounded bg-red-500/15 px-1.5 py-0.5 text-xs text-red-200">Ninguém faz esta zona neste dia</span>
                                @endif
                                <a href="{{ route('zonas.index') }}#zona-{{ $zona->id }}" class="ml-1 text-xs text-slate-400 underline">mudar</a>
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ $paragens->count() }} {{ $paragens->count() === 1 ? 'entrega' : 'entregas' }}
                                @if($paragens->count() > 1) · arraste ou use as setas para pôr pela ordem da volta @endif
                            </p>
                        </div>
                        @if($paragens->count() > 1)
                            <div class="flex items-center gap-2">
                                <span class="hidden rounded bg-[#F59E0B]/15 px-2 py-1 text-xs text-amber-200" data-rota-alterada>Ordem por guardar</span>
                                <button form="ordem-rota-{{ $zona->id }}" class="rounded bg-[#22C55E] px-3 py-1.5 text-sm font-semibold text-[#0A0F1A]">Guardar ordem</button>
                            </div>
                        @endif
                    </header>

                    @if($paragens->isEmpty())
                        <p class="p-4 text-sm text-slate-400">Sem entregas. Marque-as na lista e carregue em <strong class="text-slate-200">{{ $zona->nome }}</strong>.</p>
                    @else
                        <ol class="divide-y divide-white/5" data-rota-lista>
                            @foreach($paragens as $paragem)
                                @php($atribuicao = $paragem['atribuicao'])
                                <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5" data-rota-item>
                                    <input type="hidden" form="ordem-rota-{{ $zona->id }}" name="ordens[{{ $atribuicao->id }}]" value="{{ $loop->iteration }}" data-rota-ordem>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <span class="hidden cursor-grab select-none px-1 text-slate-500 hover:text-slate-200 sm:inline" title="Arrastar" data-rota-pega>&#8942;&#8942;</span>
                                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-sm font-semibold text-white" style="background: {{ $cores[$zona->id] }}" data-rota-numero>{{ $loop->iteration }}</span>
                                        <span class="flex flex-col">
                                            <button type="button" class="px-1 text-xs leading-none text-slate-400 hover:text-white" title="Subir" data-rota-subir>&#9650;</button>
                                            <button type="button" class="px-1 text-xs leading-none text-slate-400 hover:text-white" title="Descer" data-rota-descer>&#9660;</button>
                                        </span>
                                    </div>

                                    <div class="min-w-0 flex-1 basis-48">
                                        <p class="truncate font-semibold text-white">{{ $paragem['nome'] }}@if($atribuicao->zona_automatica) <span class="ml-1 align-middle text-[10px] font-normal uppercase tracking-wide text-slate-500" title="Posta nesta zona pelo código postal. Se a mudar à mão, fica como deixar.">auto</span>@endif</p>
                                        <p class="truncate text-xs text-slate-400">
                                            @if($paragem['cp'] || $paragem['localidade'])<span class="text-slate-200">{{ trim($paragem['cp'].' '.$paragem['localidade']) }}</span> · @endif{{ $paragem['morada'] ?: 'Morada por definir' }}@if($paragem['tipo'] === 'corporate' && $paragem['detalhe']) · {{ $paragem['detalhe'] }}@endif
                                        </p>
                                    </div>

                                    <div class="ml-auto flex shrink-0 items-center gap-1">
                                    <form method="post" action="{{ route('entregas.atribuicoes.update', $atribuicao) }}">
                                        @csrf
                                        @method('put')
                                        <input type="hidden" name="tipo" value="{{ $atribuicao->tipo }}">
                                        <input type="hidden" name="corporate_id" value="{{ $atribuicao->corporate_id }}">
                                        <input type="hidden" name="woo_order_id" value="{{ $atribuicao->woo_order_id }}">
                                        <input type="hidden" name="dia_semana" value="{{ $atribuicao->dia_semana }}">
                                        <select name="zona_id" title="Passar para outra zona"
                                                class="max-w-32 rounded border border-white/10 bg-[#0A0F1A] px-2 py-1 text-xs text-slate-200" data-mover>
                                            <option value="{{ $zona->id }}" selected>Passar para…</option>
                                            @foreach($zonas as $outraZona)
                                                @continue($outraZona->id === $zona->id)
                                                <option value="{{ $outraZona->id }}">{{ $outraZona->nome }}</option>
                                            @endforeach
                                        </select>
                                    </form>

                                    <form method="post" action="{{ route('entregas.atribuicoes.destroy', $atribuicao) }}"
                                          data-remover="{{ $paragem['nome'] }}">
                                        @csrf
                                        @method('delete')
                                        <button class="rounded px-2 py-1 text-slate-500 hover:bg-red-500/15 hover:text-red-200" title="Tirar desta zona">&#10005;</button>
                                    </form>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    <script>
        (() => {
            /* ── Lista de entregas: filtros e selecao ── */
            const painel = document.querySelector('[data-entregas]');
            const linhas = [...painel.querySelectorAll('[data-entrega]')];
            const texto = painel.querySelector('[data-filtro-texto]');
            const selVisiveis = painel.querySelector('[data-selecionar-visiveis]');
            const vazia = painel.querySelector('[data-lista-vazia]');
            const barraVazia = painel.querySelector('[data-barra-vazia]');
            const barraAtiva = painel.querySelector('[data-barra-ativa]');
            const contagem = painel.querySelector('[data-contagem]');

            const guardado = (() => { try { return JSON.parse(sessionStorage.getItem('rotas-filtros') || '{}'); } catch { return {}; } })();
            const filtros = {
                estado: guardado.estado ?? ({{ $porAtribuir }} > 0 ? 'pendentes' : 'todas'),
                tipo: guardado.tipo ?? '',
            };

            const visiveis = () => linhas.filter((l) => ! l.hidden);

            const atualizarSelecao = () => {
                const n = linhas.filter((l) => l.querySelector('[data-entrega-check]').checked).length;
                contagem.textContent = n;
                barraVazia.classList.toggle('hidden', n > 0);
                barraAtiva.classList.toggle('hidden', n === 0);
                const vis = visiveis();
                const marcadas = vis.filter((l) => l.querySelector('[data-entrega-check]').checked).length;
                selVisiveis.checked = vis.length > 0 && marcadas === vis.length;
                selVisiveis.indeterminate = marcadas > 0 && marcadas < vis.length;
            };

            const aplicar = () => {
                const termos = texto.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
                linhas.forEach((l) => {
                    l.hidden = (filtros.estado === 'pendentes' && l.dataset.atribuida === '1')
                        || (filtros.tipo && l.dataset.tipo !== filtros.tipo)
                        || ! termos.every((t) => l.dataset.busca.includes(t));
                });
                painel.querySelectorAll('[data-filtro-estado]').forEach((b) => marcarBotao(b, b.dataset.filtroEstado === filtros.estado));
                painel.querySelectorAll('[data-filtro-tipo]').forEach((b) => marcarBotao(b, b.dataset.filtroTipo === filtros.tipo));
                const nenhuma = visiveis().length === 0;
                vazia.classList.toggle('hidden', ! nenhuma);
                const soPendentes = filtros.estado === 'pendentes' && ! termos.length && ! filtros.tipo;
                vazia.querySelector('[data-vazia-pendentes]')?.classList.toggle('hidden', ! soPendentes);
                vazia.querySelector('[data-vazia-filtro]')?.classList.toggle('hidden', soPendentes);
                try { sessionStorage.setItem('rotas-filtros', JSON.stringify(filtros)); } catch {}
                atualizarSelecao();
            };

            function marcarBotao(botao, ativo) {
                if (botao.closest('[data-lista-vazia]')) return;
                botao.classList.toggle('bg-[#3B82F6]', ativo);
                botao.classList.toggle('text-white', ativo);
                botao.classList.toggle('text-slate-300', ! ativo);
            }

            painel.querySelectorAll('[data-filtro-estado]').forEach((b) => b.addEventListener('click', () => { filtros.estado = b.dataset.filtroEstado; aplicar(); }));
            painel.querySelectorAll('[data-filtro-tipo]').forEach((b) => b.addEventListener('click', () => { filtros.tipo = b.dataset.filtroTipo; aplicar(); }));
            texto.addEventListener('input', aplicar);
            linhas.forEach((l) => l.querySelector('[data-entrega-check]').addEventListener('change', atualizarSelecao));
            selVisiveis.addEventListener('change', () => {
                visiveis().forEach((l) => { l.querySelector('[data-entrega-check]').checked = selVisiveis.checked; });
                atualizarSelecao();
            });
            painel.querySelector('[data-limpar-selecao]').addEventListener('click', () => {
                linhas.forEach((l) => { l.querySelector('[data-entrega-check]').checked = false; });
                atualizarSelecao();
            });

            aplicar();

            /* ── Rotas: ordenar ── */
            let ordemPorGuardar = false;

            document.querySelectorAll('[data-rota]').forEach((rota) => {
                const lista = rota.querySelector('[data-rota-lista]');
                const aviso = rota.querySelector('[data-rota-alterada]');
                if (! lista) return;

                const renumerar = () => {
                    lista.querySelectorAll('[data-rota-item]').forEach((item, i) => {
                        item.querySelector('[data-rota-numero]').textContent = i + 1;
                        item.querySelector('[data-rota-ordem]').value = i + 1;
                    });
                    aviso?.classList.remove('hidden');
                    ordemPorGuardar = true;
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

                rota.querySelector(`form[id^="ordem-rota-"]`).addEventListener('submit', () => { ordemPorGuardar = false; });
            });

            const perderOrdem = () => ! ordemPorGuardar || confirm('Há uma ordem de rota por guardar que se vai perder. Continuar?');

            /* ── Passar para outro colaborador / tirar da rota ── */
            document.querySelectorAll('[data-mover]').forEach((select) => {
                const original = select.value;
                select.addEventListener('change', () => {
                    if (select.value === original) return;
                    if (! perderOrdem()) { select.value = original; return; }
                    ordemPorGuardar = false;
                    select.form.submit();
                });
            });

            document.querySelectorAll('[data-remover]').forEach((form) => form.addEventListener('submit', (e) => {
                if (! confirm(`Tirar "${form.dataset.remover}" desta zona? Volta a ficar sem zona.`) || ! perderOrdem()) {
                    e.preventDefault();
                    return;
                }
                ordemPorGuardar = false;
            }));

            document.getElementById('form-atribuir').addEventListener('submit', (e) => {
                if (! perderOrdem()) { e.preventDefault(); return; }
                ordemPorGuardar = false;
            });

            window.addEventListener('beforeunload', (e) => {
                if (ordemPorGuardar) { e.preventDefault(); e.returnValue = ''; }
            });
        })();
    </script>
</x-layouts.app>
