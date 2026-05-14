<x-layouts.app title="Minhas entregas">
    <x-page-title title="Minhas entregas" subtitle="{{ now()->format('d/m/Y') }}" />
    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 sm:grid-cols-[2fr_1fr_auto]">
        <label class="text-sm text-slate-300">Pesquisar
            <input name="q" value="{{ $q }}" placeholder="Empresa..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Estado
            <select name="status" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="pendente" @selected($status === 'pendente')>Pendentes</option>
                <option value="entregue" @selected($status === 'entregue')>Entregues</option>
                <option value="falhou" @selected($status === 'falhou')>Nao entregues</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('minhas-entregas.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>
    <div class="grid gap-4">
        @forelse($registos as $registo)
            <div class="rounded border border-white/10 bg-[#151E2D] p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        @if($registo->tipo === 'b2c')
                            <a href="{{ route('minhas-entregas.show', $registo) }}" class="font-semibold text-white hover:text-[#22C55E]">#{{ $registo->wooOrder->woo_id }} {{ $registo->wooOrder->billing_name ?: 'Cliente B2C' }}</a>
                            <p class="text-sm text-slate-300">B2C</p>
                            <p class="text-sm text-slate-400">{{ $registo->wooOrder->billing_phone ?: $registo->wooOrder->billing_email ?: 'Sem contacto' }}</p>
                        @else
                            <a href="{{ route('minhas-entregas.show', $registo) }}" class="font-semibold text-white hover:text-[#22C55E]">{{ $registo->corporate->empresa }}</a>
                            @if($registo->corporate->sucursal)
                                <p class="text-sm text-slate-300">{{ $registo->corporate->sucursal }}</p>
                            @endif
                            <p class="text-sm text-slate-300">{{ $registo->corporate->responsavel_nome ?: 'Responsavel por definir' }}</p>
                            <p class="text-sm text-slate-400">{{ $registo->corporate->responsavel_telefone ?: 'Sem telefone' }}</p>
                            <p class="mt-2 text-sm text-slate-400">{{ $registo->corporate->moradaParaEntrega() ?: 'Morada por definir' }}</p>
                        @endif
                    </div>
                    <span class="rounded px-3 py-1 text-xs font-semibold {{ $registo->status === 'entregue' ? 'bg-emerald-500/20 text-emerald-200' : ($registo->status === 'falhou' ? 'bg-red-500/20 text-red-200' : 'bg-[#F59E0B]/20 text-amber-200') }}">{{ $registo->status }}</span>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <a href="{{ route('minhas-entregas.show', $registo) }}" class="rounded bg-[#22C55E] px-4 py-2 text-center text-sm font-semibold text-[#0A0F1A]">Abrir entrega</a>
                    @if($registo->tipo === 'corporate' && $registo->corporate->googleMapsUrl())
                        <a href="{{ $registo->corporate->googleMapsUrl() }}" target="_blank" rel="noopener" class="rounded bg-[#3B82F6] px-4 py-2 text-center text-sm font-semibold text-white">Google Maps</a>
                    @endif
                    @if($registo->tipo === 'corporate' && $registo->corporate->wazeUrl())
                        <a href="{{ $registo->corporate->wazeUrl() }}" target="_blank" rel="noopener" class="rounded bg-white/10 px-4 py-2 text-center text-sm font-semibold text-slate-200">Waze</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded border border-white/10 bg-[#151E2D] p-4 text-slate-400">Nao tem entregas atribuidas para hoje.</p>
        @endforelse
    </div>
</x-layouts.app>
