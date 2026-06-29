<x-layouts.app title="Nova Fatura — Upload">
    <x-page-title title="Enviar Fatura" subtitle="PDF (todas as páginas automaticamente) ou várias imagens JPG/PNG">
        <a href="{{ route('invoices.index') }}"
           class="rounded-lg border border-white/10 bg-[#151E2D] px-4 py-2 text-sm text-slate-300 hover:bg-white/10">
            ← Voltar
        </a>
    </x-page-title>

    <div class="mx-auto max-w-xl">
        <form method="post" action="{{ route('invoices.upload.store') }}" enctype="multipart/form-data" id="upload-form">
            @csrf

            {{-- Drop zone --}}
            <div class="relative cursor-pointer rounded-xl border-2 border-dashed border-white/20 bg-[#151E2D] p-8 text-center transition-colors hover:border-emerald-500/40"
                 id="drop-zone">

                <input type="file" name="invoice_files[]" id="invoice_files"
                       accept=".pdf,.jpg,.jpeg,.png" class="hidden" multiple>

                <div id="drop-idle">
                    <svg class="mx-auto mb-4 h-12 w-12 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                    </svg>
                    <p class="mb-1 text-base font-semibold text-slate-300">Arraste os ficheiros para aqui</p>
                    <p class="text-sm text-slate-500">ou clique para selecionar</p>
                    <div class="mt-3 space-y-1 text-xs text-slate-600">
                        <p>PDF — todas as páginas extraídas automaticamente</p>
                        <p>JPG / PNG — selecione várias imagens para faturas multi-página</p>
                        <p>Máx. {{ round(config('invoices.max_upload_size', 10240) / 1024) }} MB por ficheiro · até 20 ficheiros</p>
                    </div>
                </div>

                <div id="drop-selected" class="hidden text-left">
                    <div class="mb-3 flex items-center gap-2">
                        <svg class="h-5 w-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-semibold text-emerald-300" id="selected-summary">—</span>
                    </div>
                    <ul id="file-list" class="space-y-1 text-xs text-slate-400 max-h-40 overflow-y-auto"></ul>
                    <button type="button" onclick="resetFiles()" class="mt-3 text-xs text-slate-500 underline hover:text-slate-400">
                        Escolher outros ficheiros
                    </button>
                </div>
            </div>

            {{-- Validation error --}}
            @error('invoice_files')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
            @error('invoice_files.*')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror

            {{-- JS validation message --}}
            <p id="js-error" class="mt-2 hidden text-sm text-red-400"></p>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('invoices.index') }}"
                   class="rounded-lg border border-white/10 bg-transparent px-5 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancelar
                </a>
                <button type="submit" id="submit-btn"
                        class="rounded-lg bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
                    Enviar e processar
                </button>
            </div>
        </form>
    </div>

    <script>
        const dropZone   = document.getElementById('drop-zone');
        const fileInput  = document.getElementById('invoice_files');
        const dropIdle   = document.getElementById('drop-idle');
        const dropSel    = document.getElementById('drop-selected');
        const summary    = document.getElementById('selected-summary');
        const fileList   = document.getElementById('file-list');
        const submitBtn  = document.getElementById('submit-btn');
        const jsError    = document.getElementById('js-error');
        const form       = document.getElementById('upload-form');

        function showFiles(files) {
            if (!files || files.length === 0) return;

            const hasPdf = Array.from(files).some(f => f.type === 'application/pdf');

            // Validate: PDF + multiple files → error
            if (hasPdf && files.length > 1) {
                jsError.textContent = 'Para um PDF, selecione apenas um ficheiro. Para várias páginas, use imagens JPG/PNG.';
                jsError.classList.remove('hidden');
                resetFiles();
                return;
            }
            jsError.classList.add('hidden');

            dropIdle.classList.add('hidden');
            dropSel.classList.remove('hidden');
            dropZone.classList.add('border-emerald-500/50');

            const total = Array.from(files).reduce((s, f) => s + f.size, 0);
            summary.textContent = files.length === 1
                ? `${files[0].name} (${formatSize(total)})`
                : `${files.length} ficheiros · ${formatSize(total)} total`;

            fileList.innerHTML = '';
            Array.from(files).forEach((f, i) => {
                const li = document.createElement('li');
                li.className = 'flex items-center gap-1.5';
                li.innerHTML = `<span class="text-slate-500">${i + 1}.</span> <span>${f.name}</span> <span class="text-slate-600">(${formatSize(f.size)})</span>`;
                fileList.appendChild(li);
            });
        }

        function resetFiles() {
            fileInput.value = '';
            dropSel.classList.add('hidden');
            dropIdle.classList.remove('hidden');
            dropZone.classList.remove('border-emerald-500/50');
            fileList.innerHTML = '';
        }

        function formatSize(bytes) {
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        }

        dropZone.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON') return;
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) showFiles(fileInput.files);
        });

        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('border-emerald-500/50', 'bg-emerald-900/10');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('bg-emerald-900/10');
        });

        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('bg-emerald-900/10');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const dt = new DataTransfer();
                Array.from(files).forEach(f => dt.items.add(f));
                fileInput.files = dt.files;
                showFiles(files);
            }
        });

        form.addEventListener('submit', (e) => {
            if (fileInput.files.length === 0) {
                e.preventDefault();
                jsError.textContent = 'Selecione pelo menos um ficheiro.';
                jsError.classList.remove('hidden');
                return;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = fileInput.files.length > 1
                ? `A enviar ${fileInput.files.length} páginas...`
                : 'A enviar...';
        });
    </script>
</x-layouts.app>
