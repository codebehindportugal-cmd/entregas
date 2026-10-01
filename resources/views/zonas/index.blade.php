<x-layouts.app title="Zonas">
    <x-page-title title="Zonas" subtitle="Quem faz cada zona, férias e substituições" />

    {{-- ─────────── Conversao das atribuicoes antigas ─────────── --}}
    @if($porConverter->isNotEmpty())
        <section id="converter" class="mb-6 scroll-mt-4 rounded border border-amber-400/30 bg-[#F59E0B]/10 p-5">
            <h2 class="text-lg font-semibold text-white">Passar as entregas antigas para zonas</h2>
            <p class="mt-1 text-sm text-slate-300">
                Estas entregas ainda estão atribuídas a colaboradores, de antes das zonas. Escolha a zona que cada um faz:
                as entregas dele passam para essa zona e, nos dias em que as fazia, fica ele a fazer a zona (se ainda não tiver ninguém).
            </p>
            <form method="post" action="{{ route('zonas.converter') }}" class="mt-4">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                                <th class="py-2 pr-4">Colaborador</th>
                                <th class="py-2 pr-4">Entregas</th>
                                <th class="py-2 pr-4">Dias</th>
                                <th class="py-2">Passa para a zona</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($porConverter as $linha)
                                <tr class="border-t border-white/10">
                                    <td class="py-2 pr-4 font-semibold text-white">{{ $linha['user']->name }}</td>
                                    <td class="py-2 pr-4 text-slate-300">{{ $linha['total'] }}</td>
                                    <td class="py-2 pr-4 text-slate-300">{{ $linha['dias']->implode(', ') }}</td>
                                    <td class="py-2">
                                        <select name="zona[{{ $linha['user']->id }}]" class="w-full min-w-40 rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                            <option value="">— deixar para depois —</option>
                                            @foreach($zonas->where('ativo', true) as $zona)
                                                <option value="{{ $zona->id }}">{{ $zona->nome }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="mt-4 rounded bg-[#F59E0B] px-4 py-2 font-semibold text-[#0A0F1A]" data-converter>Converter</button>
            </form>
        </section>
    @endif

    {{-- ─────────── Horario ─────────── --}}
    <section class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="text-lg font-semibold text-white">Quem faz cada zona</h2>
        <p class="mt-1 text-sm text-slate-400">O habitual de cada semana. "= Zona X" quer dizer que nesse dia a zona vai com quem faz a Zona X (também nas férias dessa pessoa). Para férias ou faltas use as substituições, em baixo.</p>

        <form method="post" action="{{ route('zonas.horario') }}" class="mt-4">
            @csrf
            @method('put')
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="py-2 pr-3">Zona</th>
                            @foreach($dias as $dia)
                                <th class="px-1 py-2">{{ $dia === 'Terca' ? 'Terça' : ($dia === 'Sabado' ? 'Sábado' : $dia) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zonas->where('ativo', true) as $zona)
                            @php($horario = $zona->horarios->keyBy('dia_semana'))
                            <tr id="zona-{{ $zona->id }}" class="scroll-mt-4 border-t border-white/10">
                                <td class="w-48 py-2 pr-3 align-middle">
                                    <span class="whitespace-nowrap font-semibold text-white"><span class="mr-1.5 inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $zona->cor }}"></span>{{ $zona->nome }}</span>
                                    @if($zona->descricao)<span class="block max-w-48 text-xs leading-snug text-slate-400">{{ $zona->descricao }}</span>@endif
                                </td>
                                @foreach($dias as $dia)
                                    <td class="px-1 py-2">
                                        <select name="horario[{{ $zona->id }}][{{ $dia }}]" aria-label="{{ $zona->nome }} à {{ $dia }}"
                                                class="w-full min-w-24 rounded border border-white/10 bg-[#0A0F1A] px-2 py-1.5 text-sm text-white">
                                            <option value="">—</option>
                                            <optgroup label="Colaborador">
                                                @foreach($colaboradores as $colaborador)
                                                    <option value="u:{{ $colaborador->id }}" @selected((int) $horario->get($dia)?->user_id === $colaborador->id)>{{ $colaborador->name }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Vai com quem faz">
                                                @foreach($zonas->where('ativo', true) as $outra)
                                                    @continue($outra->id === $zona->id)
                                                    <option value="z:{{ $outra->id }}" @selected((int) $horario->get($dia)?->acompanha_zona_id === $outra->id)>= {{ $outra->nome }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button class="mt-4 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
        </form>
    </section>

    {{-- ─────────── Substituicoes ─────────── --}}
    <section class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="text-lg font-semibold text-white">Férias e substituições</h2>
        <p class="mt-1 text-sm text-slate-400">Entre estas datas a zona é feita por outra pessoa. Depois volta ao habitual sozinha.</p>

        <form method="post" action="{{ route('zonas.substituicoes.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto_1fr_auto] lg:items-end">
            @csrf
            <label class="text-sm text-slate-300">Zona
                <select name="zona_id" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($zonas->where('ativo', true) as $zona)
                        <option value="{{ $zona->id }}" @selected((int) old('zona_id') === $zona->id)>{{ $zona->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Feita por
                <select name="user_id" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($colaboradores as $colaborador)
                        <option value="{{ $colaborador->id }}" @selected((int) old('user_id') === $colaborador->id)>{{ $colaborador->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">De
                <input type="date" name="inicio" required value="{{ old('inicio', now()->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-300">Até
                <input type="date" name="fim" required value="{{ old('fim', now()->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-300">Nota
                <input name="nota" value="{{ old('nota') }}" placeholder="Ex.: férias do João" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <button class="rounded bg-[#3B82F6] px-4 py-2 font-semibold text-white">Adicionar</button>
        </form>

        @php($substituicoes = $zonas->flatMap->substituicoes->sortBy('inicio'))
        @if($substituicoes->isEmpty())
            <p class="mt-4 text-sm text-slate-400">Sem substituições marcadas.</p>
        @else
            <ul class="mt-4 divide-y divide-white/10 text-sm">
                @foreach($substituicoes as $substituicao)
                    @php($zona = $zonas->firstWhere('id', $substituicao->zona_id))
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                        <span class="text-slate-200">
                            <span class="mr-1.5 inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $zona?->cor }}"></span>
                            <strong class="text-white">{{ $zona?->nome }}</strong> feita por <strong class="text-white">{{ $substituicao->user?->name }}</strong>
                            de {{ $substituicao->inicio->format('d/m/Y') }} a {{ $substituicao->fim->format('d/m/Y') }}
                            @if($substituicao->inicio->isPast() || $substituicao->inicio->isToday())
                                <span class="ml-1 rounded bg-[#F59E0B]/15 px-1.5 py-0.5 text-xs text-amber-200">a decorrer</span>
                            @endif
                            @if($substituicao->nota)<span class="text-slate-400"> · {{ $substituicao->nota }}</span>@endif
                        </span>
                        <form method="post" action="{{ route('zonas.substituicoes.destroy', $substituicao) }}" onsubmit="return confirm('Apagar esta substituição?')">
                            @csrf
                            @method('delete')
                            <button class="rounded px-2 py-1 text-slate-400 hover:bg-red-500/15 hover:text-red-200">Apagar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ─────────── Zonas ─────────── --}}
    <section class="rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="text-lg font-semibold text-white">Zonas</h2>
        <div class="mt-4 grid gap-2">
            @foreach($zonas as $zona)
                <form method="post" action="{{ route('zonas.update', $zona) }}" class="grid items-end gap-3 rounded border border-white/10 p-3 sm:grid-cols-[auto_1fr_6rem_auto_auto] {{ $zona->ativo ? '' : 'opacity-60' }}">
                    <label class="text-xs text-slate-400 sm:col-span-5 sm:grid sm:grid-cols-[2fr_2fr_1fr] sm:gap-3">
                        <span>Área
                            <input name="descricao" value="{{ $zona->descricao }}" placeholder="Ex.: de Cantanhede a Porto de Mós" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                        </span>
                        <span>Códigos postais <span class="text-slate-500">(para sugerir a zona; ex.: 2300-2599, 3000-3299)</span>
                            <input name="codigos_postais" value="{{ $zona->codigos_postais }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                        </span>
                        <span>Partida da volta <span class="text-slate-500">(código postal; vazio = Caldas)</span>
                            <input name="partida_cp" value="{{ $zona->partida_cp }}" placeholder="2500" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                        </span>
                    </label>
                    @csrf
                    @method('put')
                    <label class="text-xs text-slate-400">Cor
                        <input type="color" name="cor" value="{{ $zona->cor }}" class="mt-1 block h-9 w-12 cursor-pointer rounded border border-white/10 bg-transparent">
                    </label>
                    <label class="text-xs text-slate-400">Nome
                        <input name="nome" value="{{ $zona->nome }}" required class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                    </label>
                    <label class="text-xs text-slate-400">Ordem
                        <input type="number" name="ordem" min="0" value="{{ $zona->ordem }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                    </label>
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-300">
                        <input type="checkbox" name="ativo" value="1" @checked($zona->ativo) class="rounded border-white/10 bg-[#0A0F1A]">
                        Ativa <span class="text-xs text-slate-500">({{ $zona->atribuicoes_count }} entregas)</span>
                    </label>
                    <button class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Guardar</button>
                </form>
            @endforeach
        </div>

        <form method="post" action="{{ route('zonas.store') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-white/10 pt-4">
            @csrf
            <label class="text-xs text-slate-400">Cor
                <input type="color" name="cor" value="#64748B" class="mt-1 block h-9 w-12 cursor-pointer rounded border border-white/10 bg-transparent">
            </label>
            <label class="flex-1 text-xs text-slate-400">Nova zona
                <input name="nome" required placeholder="Ex.: Margem Sul" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
            </label>
            <button class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Criar zona</button>
        </form>
    </section>
</x-layouts.app>
