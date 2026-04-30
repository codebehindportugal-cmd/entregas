<x-layouts.app title="Entrega">
    <x-page-title title="{{ $registoEntrega->corporate->empresa }}" subtitle="{{ $registoEntrega->data_entrega->format('d/m/Y') }}" />
    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">Responsavel</p>
            <p class="mt-1 font-semibold text-white">{{ $registoEntrega->corporate->responsavel_nome ?: 'Por definir' }}</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">Telemovel</p>
            @if($registoEntrega->corporate->responsavel_telefone)
                <a href="tel:{{ $registoEntrega->corporate->responsavel_telefone }}" class="mt-1 block font-semibold text-[#22C55E]">{{ $registoEntrega->corporate->responsavel_telefone }}</a>
            @else
                <p class="mt-1 font-semibold text-white">Por definir</p>
            @endif
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">Morada</p>
            @if($registoEntrega->corporate->fatura_morada)
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($registoEntrega->corporate->fatura_morada) }}" target="_blank" rel="noopener" class="mt-1 block font-semibold text-[#3B82F6]">{{ $registoEntrega->corporate->fatura_morada }}</a>
            @else
                <p class="mt-1 font-semibold text-white">Por definir</p>
            @endif
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" action="{{ route('minhas-entregas.update', $registoEntrega) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @method('put')
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach(['pendente' => 'Pendente', 'entregue' => 'Entregue', 'falhou' => 'Falhou'] as $value => $label)
                <label class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-3 text-sm text-slate-200">
                    <input type="radio" name="status" value="{{ $value }}" @checked(old('status', $registoEntrega->status) === $value)> {{ $label }}
                </label>
            @endforeach
        </div>
        <label class="mt-5 block text-sm text-slate-300">Nota
            <textarea name="nota" rows="4" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('nota', $registoEntrega->nota) }}</textarea>
        </label>
        <label class="mt-5 block text-sm text-slate-300">Fotos
            <input name="fotos[]" type="file" accept="image/*" capture="environment" multiple class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        @if($registoEntrega->fotos)
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach($registoEntrega->fotos as $foto)
                    <a href="{{ asset('storage/'.$foto) }}" target="_blank" class="block">
                        <img src="{{ asset('storage/'.$foto) }}" class="aspect-square rounded object-cover" alt="Foto entrega">
                    </a>
                @endforeach
            </div>
        @endif
        <button class="mt-6 w-full rounded bg-[#22C55E] px-4 py-3 font-semibold text-[#0A0F1A]">Guardar entrega</button>
    </form>
</x-layouts.app>
