<x-layouts.app title="Listas de cabazes">
    <x-page-title title="Listas de cabazes" subtitle="Produtos e quantidades de cada tamanho, semana a semana" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('lista-cabazes.store') }}" class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-[#14532d]">Nova semana</h2>
        <div class="grid gap-3 lg:grid-cols-[110px_110px_1fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700">Semana
                <input name="semana_numero" type="number" min="1" max="53" value="{{ old('semana_numero', $sugestao['semana_numero']) }}" required
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Ano
                <input name="ano" type="number" min="2020" max="2100" value="{{ old('ano', $sugestao['ano']) }}" required
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Mes
                <select name="mes" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                    @foreach(\App\Models\ListaCabaz::meses() as $numero => $nome)
                        <option value="{{ $numero }}" @selected(old('mes', $sugestao['mes']) == $numero)>{{ $nome }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Copiar de
                <select name="copiar_de" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                    <option value="">Comecar vazia</option>
                    @foreach($listas as $outra)
                        <option value="{{ $outra->id }}">{{ $outra->tituloFormatado() }} ({{ $outra->itens_count }} linhas)</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Criar</button>
            </div>
        </div>
    </form>

    <form method="get" class="mb-4 flex items-end gap-2">
        <label class="text-sm font-medium text-slate-700">Ano
            <select name="ano" onchange="this.form.submit()" class="mt-1 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                @foreach($anos as $opcao)
                    <option value="{{ $opcao }}" @selected($opcao == $ano)>{{ $opcao }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div class="overflow-x-auto rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Semana</th>
                    <th class="p-3">Estado</th>
                    <th class="p-3">Linhas</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($listas as $lista)
                    <tr class="border-t border-slate-100">
                        <td class="p-3 font-medium text-slate-800">{{ $lista->tituloFormatado() }}</td>
                        <td class="p-3">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $lista->estado === 'publicada' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $lista->estado === 'publicada' ? 'Publicada' : 'Rascunho' }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-600">{{ $lista->itens_count }}</td>
                        <td class="p-3 text-right">
                            <a href="{{ route('lista-cabazes.edit', $lista) }}" class="rounded bg-[#3B82F6] px-3 py-1 text-xs font-semibold text-white">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-slate-500">Ainda nao ha listas para {{ $ano }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
