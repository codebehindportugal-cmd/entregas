<x-layouts.app title="Verificacao de entregas">
    @php
        $sortUrl = fn (string $column) => route('entregas.verificacao', array_merge(request()->query(), [
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ]));
        $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
        $periodUrl = fn (string $value) => route('entregas.verificacao', array_merge(request()->query(), ['periodo' => $value]));
    @endphp
    <x-page-title title="Verificacao" subtitle="{{ \Illuminate\Support\Carbon::parse($inicioPeriodo)->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($fimPeriodo)->format('d/m/Y') }}" />

    <form method="get" class="mb-6 rounded border border-white/10 bg-[#151E2D] p-4">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach(['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mes'] as $value => $label)
                <a href="{{ $periodUrl($value) }}" class="rounded px-3 py-2 text-sm font-medium {{ $periodo === $value ? 'bg-[#3B82F6] text-white' : 'bg-white/10 text-slate-300' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_2fr_auto]">
        <input type="hidden" name="periodo" value="{{ $periodo }}">
        <label class="text-sm text-slate-300">Data
            <input name="data" type="date" value="{{ $data }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Estado
            <select name="status" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="pendente" @selected($status === 'pendente')>Pendentes</option>
                <option value="entregue" @selected($status === 'entregue')>Entregues</option>
                <option value="falhou" @selected($status === 'falhou')>Nao entregues</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Colaborador
            <select name="user_id" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="0">Todos</option>
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->id }}" @selected($userId === $colaborador->id)>{{ $colaborador->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">Pesquisar empresa
            <input name="q" value="{{ $q }}" placeholder="Empresa, sucursal ou morada..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('entregas.verificacao') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('entregas.verificacao', ['data' => $data, 'periodo' => $periodo, 'status' => 'pendente', 'user_id' => $userId, 'q' => $q]) }}" class="rounded border border-[#F59E0B]/30 bg-[#F59E0B]/10 p-4">
            <p class="text-sm text-amber-200">Pendentes</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ (int) ($resumo->pendentes ?? 0) }}</p>
        </a>
        <a href="{{ route('entregas.verificacao', ['data' => $data, 'periodo' => $periodo, 'status' => 'entregue', 'user_id' => $userId, 'q' => $q]) }}" class="rounded border border-emerald-400/30 bg-emerald-500/10 p-4">
            <p class="text-sm text-emerald-200">Entregues</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ (int) ($resumo->entregues ?? 0) }}</p>
        </a>
        <a href="{{ route('entregas.verificacao', ['data' => $data, 'periodo' => $periodo, 'status' => 'falhou', 'user_id' => $userId, 'q' => $q]) }}" class="rounded border border-red-400/30 bg-red-500/10 p-4">
            <p class="text-sm text-red-200">Nao entregues</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ (int) ($resumo->falhadas ?? 0) }}</p>
        </a>
    </div>

    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400">
                <tr>
                    @if($periodo !== 'dia')
                        <th class="p-3"><a href="{{ $sortUrl('data') }}">Data{{ $sortMark('data') }}</a></th>
                    @endif
                    <th class="p-3"><a href="{{ $sortUrl('empresa') }}">Empresa{{ $sortMark('empresa') }}</a></th>
                    <th class="p-3"><a href="{{ $sortUrl('colaborador') }}">Colaborador{{ $sortMark('colaborador') }}</a></th>
                    <th class="p-3"><a href="{{ $sortUrl('estado') }}">Estado{{ $sortMark('estado') }}</a></th>
                    <th class="p-3"><a href="{{ $sortUrl('hora') }}">Hora{{ $sortMark('hora') }}</a></th>
                    <th class="p-3">Fotos</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($registos as $registo)
                    <tr class="border-t border-white/10">
                        @if($periodo !== 'dia')
                            <td class="p-3 text-slate-300">{{ $registo->data_entrega->format('d/m/Y') }}</td>
                        @endif
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $registo->corporate->empresa }}</p>
                            <p class="text-xs text-slate-400">{{ $registo->corporate->moradaParaEntrega() ?: $registo->corporate->sucursal }}</p>
                        </td>
                        <td class="p-3 text-slate-300">
                            <span class="mr-2 inline-block h-3 w-3 rounded-full" style="background: {{ $registo->user->cor }}"></span>
                            {{ $registo->user->name }}
                        </td>
                        <td class="p-3">
                            <span class="rounded px-3 py-1 text-xs font-semibold {{ $registo->status === 'entregue' ? 'bg-emerald-500/20 text-emerald-200' : ($registo->status === 'falhou' ? 'bg-red-500/20 text-red-200' : 'bg-[#F59E0B]/20 text-amber-200') }}">
                                {{ $registo->status === 'falhou' ? 'nao entregue' : $registo->status }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-300">{{ $registo->hora_entrega ? $registo->hora_entrega->format('H:i') : '-' }}</td>
                        <td class="p-3 text-slate-300">{{ count($registo->fotos ?? []) }}</td>
                        <td class="p-3 text-right">
                            <a class="text-[#3B82F6]" href="{{ route('minhas-entregas.show', $registo) }}">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $periodo !== 'dia' ? 7 : 6 }}" class="p-4 text-slate-400">Sem entregas para os filtros escolhidos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
