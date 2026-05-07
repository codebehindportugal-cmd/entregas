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
        <label class="text-sm text-slate-300">Pesquisar empresa
            <input name="q" value="{{ $q }}" placeholder="Empresa, sucursal ou morada..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
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
                <button @disabled($corporates->isEmpty()) class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A] disabled:cursor-not-allowed disabled:bg-slate-600 disabled:text-slate-300">Atribuir selecionadas</button>
            </div>
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
        </form>
    </div>

    <div class="mb-6 rounded border border-white/10 bg-[#151E2D] p-5">
        <h2 class="mb-4 text-lg font-semibold text-white">Atribuicao individual</h2>
        <form method="post" action="{{ route('entregas.atribuicoes.store') }}" class="grid gap-4 lg:grid-cols-4">
            @csrf
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
    <div class="grid gap-3">
        @forelse($atribuicoes as $atribuicao)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <div class="grid gap-4 lg:grid-cols-[1fr_2fr_auto] lg:items-center">
                    <div>
                        <p class="font-semibold text-white">{{ $atribuicao->corporate->empresa }}</p>
                        <p class="text-sm text-slate-400">{{ $atribuicao->corporate->horario_entrega ?: 'Horario por definir' }}</p>
                    </div>
                    <form method="post" action="{{ route('entregas.atribuicoes.update', $atribuicao) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        @csrf
                        @method('put')
                        <input type="hidden" name="corporate_id" value="{{ $atribuicao->corporate_id }}">
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
        @empty
            <p class="rounded border border-white/10 bg-[#151E2D] p-4 text-slate-400">Sem atribuicoes para este dia.</p>
        @endforelse
    </div>
</x-layouts.app>
