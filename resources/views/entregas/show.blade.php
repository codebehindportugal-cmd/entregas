<x-layouts.app title="Entrega">
    <x-page-title title="{{ $registoEntrega->tipo === 'b2c' ? '#'.$registoEntrega->wooOrder->woo_id.' '.($registoEntrega->wooOrder->billing_name ?: 'Cliente B2C') : $registoEntrega->corporate->empresa }}" subtitle="{{ $registoEntrega->data_entrega->format('d/m/Y') }}" />
    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">{{ $registoEntrega->tipo === 'b2c' ? 'Cliente' : 'Responsavel' }}</p>
            <p class="mt-1 font-semibold text-white">{{ $registoEntrega->tipo === 'b2c' ? ($registoEntrega->wooOrder->billing_name ?: 'Por definir') : ($registoEntrega->corporate->responsavel_nome ?: 'Por definir') }}</p>
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">Telemovel</p>
            @php($telefone = $registoEntrega->tipo === 'b2c' ? $registoEntrega->wooOrder->billing_phone : $registoEntrega->corporate->responsavel_telefone)
            @if($telefone)
                <a href="tel:{{ $telefone }}" class="mt-1 block font-semibold text-[#22C55E]">{{ $telefone }}</a>
            @else
                <p class="mt-1 font-semibold text-white">Por definir</p>
            @endif
        </div>
        <div class="rounded border border-white/10 bg-[#151E2D] p-4">
            <p class="text-sm text-slate-400">{{ $registoEntrega->tipo === 'b2c' ? 'Produtos' : 'Morada' }}</p>
            @if($registoEntrega->tipo === 'b2c')
                <div class="mt-1 space-y-1 text-sm font-semibold text-white">
                    @forelse($registoEntrega->wooOrder->line_items ?? [] as $produto)
                        <p>{{ $produto['quantity'] ?? 0 }}x {{ $produto['name'] ?? 'Produto' }}</p>
                    @empty
                        <p>Sem produtos</p>
                    @endforelse
                </div>
            @elseif($registoEntrega->corporate->moradaParaEntrega())
                <p class="mt-1 font-semibold text-white">{{ $registoEntrega->corporate->moradaParaEntrega() }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <a href="{{ $registoEntrega->corporate->googleMapsUrl() }}" target="_blank" rel="noopener" class="rounded bg-[#3B82F6] px-3 py-2 text-center text-sm font-semibold text-white">Google Maps</a>
                    <a href="{{ $registoEntrega->corporate->wazeUrl() }}" target="_blank" rel="noopener" class="rounded bg-white/10 px-3 py-2 text-center text-sm font-semibold text-slate-200">Waze</a>
                </div>
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
        <div class="mt-5">
            <p class="text-sm text-slate-300">Fotos</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="cursor-pointer rounded bg-[#3B82F6] px-4 py-3 text-center text-sm font-semibold text-white">
                    Tirar Foto
                    <input data-photo-input name="fotos[]" type="file" accept="image/*" capture="environment" class="sr-only">
                </label>
                <label class="cursor-pointer rounded bg-white/10 px-4 py-3 text-center text-sm font-semibold text-slate-200">
                    Escolher da Galeria
                    <input data-photo-input name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple class="sr-only">
                </label>
            </div>
            <div id="photo-preview" class="mt-4 hidden grid-cols-2 gap-3 sm:grid-cols-3"></div>
        </div>
        @if($registoEntrega->fotos)
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach($registoEntrega->fotos as $index => $foto)
                    <div class="relative overflow-hidden rounded">
                        <a href="{{ asset('storage/'.$foto) }}" target="_blank" class="block">
                            <img src="{{ asset('storage/'.$foto) }}" class="aspect-square rounded object-cover" alt="Foto entrega">
                        </a>
                        <button form="delete-photo-{{ $index }}" type="submit" class="absolute right-2 top-2 rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white shadow" onclick="return confirm('Remover esta foto?')">Remover</button>
                    </div>
                @endforeach
            </div>
        @endif
        <button class="mt-6 w-full rounded bg-[#22C55E] px-4 py-3 font-semibold text-[#0A0F1A]">Guardar entrega</button>
    </form>
    @if($registoEntrega->fotos)
        @foreach($registoEntrega->fotos as $index => $foto)
            <form id="delete-photo-{{ $index }}" method="post" action="{{ route('minhas-entregas.fotos.destroy', [$registoEntrega, $index]) }}" class="hidden">
                @csrf
                @method('delete')
            </form>
        @endforeach
    @endif
    <script>
        document.querySelectorAll('[data-photo-input]').forEach((input) => {
            input.addEventListener('change', () => {
                const preview = document.getElementById('photo-preview');
                const files = Array.from(document.querySelectorAll('[data-photo-input]'))
                    .flatMap((field) => Array.from(field.files || []));

                preview.innerHTML = '';
                preview.classList.toggle('hidden', files.length === 0);
                preview.classList.toggle('grid', files.length > 0);

                files.forEach((file) => {
                    const item = document.createElement('div');
                    item.className = 'aspect-square overflow-hidden rounded border border-white/10 bg-[#0A0F1A]';

                    if (!file.type.startsWith('image/')) {
                        item.className += ' flex items-center justify-center px-3 text-center text-xs text-slate-300';
                        item.textContent = file.name;
                        preview.appendChild(item);
                        return;
                    }

                    const image = document.createElement('img');
                    image.className = 'h-full w-full object-cover';
                    image.alt = file.name;
                    item.appendChild(image);
                    preview.appendChild(item);

                    const reader = new FileReader();
                    reader.addEventListener('load', () => {
                        image.src = reader.result;
                    });
                    reader.readAsDataURL(file);
                });
            });
        });
    </script>
</x-layouts.app>
