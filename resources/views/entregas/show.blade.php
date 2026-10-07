<x-layouts.app title="Entrega">
    @php($listaUrl = route('minhas-entregas.index', ['data' => $registoEntrega->data_entrega->toDateString()]))
    <div class="mb-4 flex items-center gap-2">
        <a href="{{ $listaUrl }}" class="rounded bg-white/10 px-3 py-2 text-sm font-semibold text-slate-200">&larr; Lista</a>
        @if($navegacao['posicao'])
            <div class="flex-1 text-center">
                <p class="text-sm font-semibold text-white">Paragem {{ $navegacao['posicao'] }} de {{ $navegacao['total'] }}</p>
                <p class="text-xs text-slate-400">{{ $navegacao['feitas'] }} feitas &middot; faltam {{ $navegacao['total'] - $navegacao['feitas'] }}</p>
            </div>
            <div class="flex gap-2">
                @if($navegacao['anterior'])
                    <a href="{{ route('minhas-entregas.show', $navegacao['anterior']) }}" class="rounded bg-white/10 px-3 py-2 text-sm font-semibold text-slate-200" aria-label="Paragem anterior">&lsaquo;</a>
                @endif
                @if($navegacao['seguinte'])
                    <a href="{{ route('minhas-entregas.show', $navegacao['seguinte']) }}" class="rounded bg-white/10 px-3 py-2 text-sm font-semibold text-slate-200" aria-label="Paragem seguinte">&rsaquo;</a>
                @endif
            </div>
        @endif
    </div>
    @if($navegacao['total'] > 0)
        <div class="mb-4 h-2 overflow-hidden rounded bg-white/10">
            <div class="h-full bg-[#22C55E]" style="width: {{ round($navegacao['feitas'] / $navegacao['total'] * 100) }}%"></div>
        </div>
    @endif
    <x-page-title title="{{ $registoEntrega->tipo === 'b2c' ? '#'.$registoEntrega->wooOrder->woo_id.' '.($registoEntrega->wooOrder->billing_name ?: 'Cliente B2C') : trim($registoEntrega->corporate->empresa.' '.($registoEntrega->corporate->sucursal ?? '')) }}" subtitle="{{ $registoEntrega->data_entrega->format('d/m/Y') }}" />
    @php($estado = $registoEntrega->status ?: 'pendente')
    <p class="mb-4">
        <span class="rounded px-3 py-1 text-xs font-semibold {{ $estado === 'entregue' ? 'bg-emerald-500/20 text-emerald-200' : ($estado === 'falhou' ? 'bg-red-500/20 text-red-200' : 'bg-[#F59E0B]/20 text-amber-200') }}">
            {{ ['pendente' => 'Por entregar', 'entregue' => 'Entregue', 'falhou' => 'Nao entregue'][$estado] ?? $estado }}
        </span>
        @if($estado === 'entregue' && $registoEntrega->hora_entrega)
            <span class="ml-2 text-xs text-slate-400">as {{ $registoEntrega->hora_entrega->format('H:i') }}</span>
        @endif
    </p>
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
        {{-- "Guardar" mantem o estado atual; os botoes grandes mudam-no e seguem para a proxima paragem. --}}
        <input type="hidden" name="status" value="{{ $estado }}">
        <label class="block text-sm text-slate-300">Nota <span class="text-slate-500">(se nao entregou, escreva o motivo)</span>
            <textarea name="nota" rows="2" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('nota', $registoEntrega->nota) }}</textarea>
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
            <p id="photo-upload-status" class="mt-3 hidden rounded border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200"></p>
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
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <button name="acao" value="entregue" class="rounded bg-[#22C55E] px-4 py-4 text-lg font-bold text-[#0A0F1A]">&check; Entregue &rarr; seguinte</button>
            <button name="acao" value="falhou" class="rounded bg-red-600 px-4 py-4 text-lg font-bold text-white">&times; Nao entregue &rarr; seguinte</button>
        </div>
        <div class="mt-3 flex flex-wrap justify-center gap-3 text-sm">
            <button name="acao" value="guardar" class="rounded bg-white/10 px-4 py-2 font-semibold text-slate-200">Guardar sem avancar</button>
            @if($estado !== 'pendente')
                <button name="acao" value="pendente" class="rounded bg-white/10 px-4 py-2 font-semibold text-slate-200">Repor por entregar</button>
            @endif
        </div>
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
        const compressibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const maxPhotoSide = 1600;
        const jpegQuality = 0.82;

        document.querySelectorAll('[data-photo-input]').forEach((input) => {
            input.addEventListener('change', async () => {
                await compressInputFiles(input);
                renderPhotoPreview();
            });
        });

        async function compressInputFiles(input) {
            if (typeof DataTransfer === 'undefined' || !input.files || input.files.length === 0) {
                return;
            }

            const status = document.getElementById('photo-upload-status');
            const dataTransfer = new DataTransfer();
            let compressedCount = 0;

            for (const file of Array.from(input.files)) {
                const compressed = await compressPhoto(file);
                if (compressed !== file) {
                    compressedCount++;
                }
                dataTransfer.items.add(compressed);
            }

            input.files = dataTransfer.files;

            if (status) {
                status.classList.toggle('hidden', compressedCount === 0);
                status.textContent = compressedCount > 0
                    ? 'Foto preparada para upload. Se continuar a falhar, o servidor pode estar sem permissao no temporario PHP ou no storage.'
                    : '';
            }
        }

        function compressPhoto(file) {
            if (!compressibleTypes.includes(file.type) || file.size < 1024 * 1024) {
                return Promise.resolve(file);
            }

            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onerror = () => resolve(file);
                reader.onload = () => {
                    const image = new Image();
                    image.onerror = () => resolve(file);
                    image.onload = () => {
                        const scale = Math.min(1, maxPhotoSide / Math.max(image.width, image.height));
                        const canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(image.width * scale));
                        canvas.height = Math.max(1, Math.round(image.height * scale));

                        const context = canvas.getContext('2d');
                        context.drawImage(image, 0, 0, canvas.width, canvas.height);

                        canvas.toBlob((blob) => {
                            if (!blob || blob.size >= file.size) {
                                resolve(file);
                                return;
                            }

                            resolve(new File([blob], file.name.replace(/\.(png|webp)$/i, '.jpg'), {
                                type: 'image/jpeg',
                                lastModified: Date.now(),
                            }));
                        }, 'image/jpeg', jpegQuality);
                    };
                    image.src = reader.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function renderPhotoPreview() {
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
        }
    </script>
</x-layouts.app>
