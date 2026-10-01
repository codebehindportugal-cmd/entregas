@php
    $numeroDe = $paragens->pluck('chave')->flip()->map(fn ($i) => $i + 1);
    $totalNaVolta = $partes->sum(fn ($parte) => $parte['paragens']->count());
    $estadoBadge = fn (string $estado) => match ($estado) {
        'entregue' => ['Entregue', 'bg-[#22C55E]/15 text-green-200'],
        'falhou' => ['Nao entregue', 'bg-red-500/15 text-red-200'],
        default => ['Por entregar', 'bg-[#F59E0B]/15 text-amber-200'],
    };
@endphp

<x-layouts.app title="Mapa da volta">
    <x-page-title
        title="{{ auth()->user()->isAdmin() ? 'Mapa das voltas' : 'Mapa da volta' }}"
        subtitle="{{ $colaborador->name }} · {{ \Illuminate\Support\Carbon::parse($data)->format('d/m/Y') }}{{ $dia ? ' · '.$dia : '' }}" />

    <form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded border border-white/10 bg-[#151E2D] p-4">
        <label class="text-sm text-slate-300">Dia
            <input name="data" type="date" value="{{ $data }}" onchange="this.form.submit()" class="mt-1 block rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        @if($colaboradores->isNotEmpty())
            <label class="text-sm text-slate-300">Colaborador
                <select name="user_id" onchange="this.form.submit()" class="mt-1 block rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($colaboradores as $opcao)
                        <option value="{{ $opcao->id }}" @selected($opcao->id === $colaborador->id)>{{ $opcao->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="flex items-center gap-2 pb-2 text-sm text-slate-300">
            <input type="checkbox" name="todas" value="1" @checked($todas) onchange="this.form.submit()" class="rounded border-white/10 bg-[#0A0F1A]">
            Incluir as já entregues
        </label>
        <a href="{{ route('minhas-entregas.index', ['data' => $data]) }}" class="ml-auto rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Ver lista de entregas</a>
    </form>

    @if($paragens->isEmpty())
        <p class="rounded border border-white/10 bg-[#151E2D] p-6 text-center text-slate-400">Sem entregas atribuídas neste dia.</p>
    @elseif($partes->isEmpty() && $semMorada->isEmpty())
        <p class="rounded border border-white/10 bg-[#151E2D] p-6 text-center text-slate-400">Está tudo entregue. 🎉</p>
    @endif

    @if($semMorada->isNotEmpty())
        <div class="mb-4 rounded border border-amber-400/30 bg-[#F59E0B]/10 p-4 text-sm text-amber-100">
            <p class="font-semibold">{{ $semMorada->count() === 1 ? 'Esta paragem não tem morada' : 'Estas paragens não têm morada' }} e não entra{{ $semMorada->count() === 1 ? '' : 'm' }} no mapa:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach($semMorada as $paragem)
                    <li>{{ $numeroDe[$paragem['chave']] }}. {{ $paragem['nome'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($partes->isNotEmpty())
        <section class="mb-6 rounded border border-white/10 bg-[#151E2D]" data-mapa>
            @if($partes->count() > 1)
                <div class="flex flex-wrap gap-2 border-b border-white/10 p-3">
                    <span class="self-center text-sm text-slate-400">A volta tem {{ $totalNaVolta }} paragens; o Google Maps só aceita 10 de cada vez:</span>
                    @foreach($partes as $i => $parte)
                        <button type="button" class="rounded px-3 py-1.5 text-sm" data-parte-botao="{{ $i }}">
                            Parte {{ $i + 1 }} <span class="opacity-70">({{ $numeroDe[$parte['paragens']->first()['chave']] }}–{{ $numeroDe[$parte['paragens']->last()['chave']] }})</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach($partes as $i => $parte)
                <div class="{{ $i > 0 ? 'hidden' : '' }}" data-parte="{{ $i }}">
                    <iframe
                        title="Percurso da parte {{ $i + 1 }}"
                        class="block h-[55vh] min-h-80 w-full border-0"
                        loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                        referrerpolicy="no-referrer-when-downgrade"
                        @if($i === 0) src="{{ $parte['embed'] }}" @else data-src="{{ $parte['embed'] }}" @endif></iframe>
                    <div class="flex flex-wrap items-center gap-3 p-3">
                        <a href="{{ $parte['navegar'] }}" target="_blank" rel="noopener"
                           class="inline-flex w-full items-center justify-center gap-2 rounded bg-[#22C55E] px-4 py-3 text-base font-semibold text-[#0A0F1A] sm:w-auto">
                            Começar a volta no Google Maps{{ $partes->count() > 1 ? ' · parte '.($i + 1) : '' }}
                        </a>
                        <span class="w-full text-xs text-slate-400 sm:w-auto">
                            @if($i === 0)
                                {{ $origem ? 'Parte do armazém' : 'Parte de onde estiver' }}
                            @else
                                Continua da paragem {{ $numeroDe[$partes[$i - 1]['paragens']->last()['chave']] }}
                            @endif
                            e segue a ordem da volta.
                        </span>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if($paragens->isNotEmpty())
        <ol class="grid gap-2">
            @foreach($paragens as $paragem)
                @php([$estadoTexto, $estadoClasses] = $estadoBadge($paragem['estado']))
                @php($foraDoMapa = ! $todas && $paragem['estado'] === 'entregue')
                <li class="flex flex-wrap items-start gap-3 rounded border border-white/10 bg-[#151E2D] p-3 {{ $foraDoMapa ? 'opacity-60' : '' }}">
                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded bg-[#3B82F6] px-2 font-semibold text-white">{{ $numeroDe[$paragem['chave']] }}</span>
                    <div class="min-w-0 flex-1 basis-56">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-white">{{ $paragem['nome'] }}</span>
                            <span class="rounded px-2 py-0.5 text-xs {{ $estadoClasses }}">{{ $estadoTexto }}</span>
                            @if($paragem['tipo'] === 'b2c')<span class="text-xs text-slate-500">B2C</span>@endif
                        </p>
                        <p class="mt-0.5 text-sm text-slate-300">{{ $paragem['morada'] ? \Illuminate\Support\Str::beforeLast($paragem['morada'], ', Portugal') : 'Morada por definir' }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            @if($paragem['horario'])Horário {{ $paragem['horario'] }}@endif
                            @if($paragem['horario'] && $paragem['contacto']) · @endif
                            @if($paragem['telefone'])
                                {{ \Illuminate\Support\Str::before($paragem['contacto'], $paragem['telefone']) }}<a href="tel:{{ preg_replace('/[^0-9+]/', '', $paragem['telefone']) }}" class="underline">{{ $paragem['telefone'] }}</a>
                            @else
                                {{ $paragem['contacto'] }}
                            @endif
                        </p>
                    </div>
                    @if($paragem['morada'])
                        <div class="flex shrink-0 gap-2">
                            <a href="https://www.google.com/maps/dir/?api=1&travelmode=driving&destination={{ rawurlencode($paragem['morada']) }}" target="_blank" rel="noopener" class="rounded bg-[#3B82F6] px-3 py-2 text-sm font-semibold text-white">Maps</a>
                            <a href="https://waze.com/ul?navigate=yes&q={{ rawurlencode($paragem['morada']) }}" target="_blank" rel="noopener" class="rounded bg-white/10 px-3 py-2 text-sm font-semibold text-slate-200">Waze</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if($partes->count() > 1)
        <script>
            (() => {
                const botoes = document.querySelectorAll('[data-parte-botao]');
                const mostrar = (n) => {
                    document.querySelectorAll('[data-parte]').forEach((parte) => {
                        const ativa = parte.dataset.parte === String(n);
                        parte.classList.toggle('hidden', ! ativa);
                        const iframe = parte.querySelector('iframe[data-src]');
                        if (ativa && iframe) { iframe.src = iframe.dataset.src; iframe.removeAttribute('data-src'); }
                    });
                    botoes.forEach((b) => {
                        const ativo = b.dataset.parteBotao === String(n);
                        b.classList.toggle('bg-[#3B82F6]', ativo);
                        b.classList.toggle('text-white', ativo);
                        b.classList.toggle('bg-white/10', ! ativo);
                        b.classList.toggle('text-slate-300', ! ativo);
                    });
                };
                botoes.forEach((b) => b.addEventListener('click', () => mostrar(b.dataset.parteBotao)));
                mostrar(0);
            })();
        </script>
    @endif
</x-layouts.app>
