<x-layouts.app title="Viaturas">
    <x-page-title title="Viaturas" subtitle="As matriculas que aparecem na preparacao e vao para a guia de transporte" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('viaturas.store') }}" class="mb-8 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-[#14532d]">Adicionar viatura</h2>
        <div class="grid gap-3 lg:grid-cols-[200px_1fr_120px_auto]">
            <label class="text-sm font-medium text-slate-700">Matricula
                <input name="matricula" type="text" value="{{ old('matricula') }}" placeholder="Ex.: 12-AB-34" required maxlength="20"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 uppercase text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Nome do carro (opcional)
                <input name="nome" type="text" value="{{ old('nome') }}" placeholder="Ex.: Carrinha branca"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                <span class="mt-1 block text-xs text-slate-500">So para ser mais facil escolher na lista. Para o Moloni vai a matricula.</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Ordem
                <input name="ordem" type="number" min="0" max="999" value="{{ old('ordem', 0) }}"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input name="ativo" type="checkbox" value="1" checked class="rounded border-slate-300">
                    Ativa
                </label>
                <button class="mb-1 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Adicionar</button>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Matricula</th>
                    <th class="p-3">Nome</th>
                    <th class="p-3 w-24">Ordem</th>
                    <th class="p-3 w-24">Ativa</th>
                    <th class="p-3 w-48">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @forelse($viaturas as $viatura)
                    <tr class="border-t border-slate-100 {{ $viatura->ativo ? '' : 'bg-slate-50 text-slate-400' }}">
                        <form method="post" action="{{ route('viaturas.update', $viatura) }}" id="viatura-{{ $viatura->id }}">
                            @csrf
                            @method('put')
                        </form>
                        <td class="p-3">
                            <input form="viatura-{{ $viatura->id }}" name="matricula" value="{{ $viatura->matricula }}" maxlength="20"
                                   class="w-full rounded border border-slate-200 px-2 py-1.5 uppercase text-slate-950">
                        </td>
                        <td class="p-3">
                            <input form="viatura-{{ $viatura->id }}" name="nome" value="{{ $viatura->nome }}"
                                   class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950">
                        </td>
                        <td class="p-3">
                            <input form="viatura-{{ $viatura->id }}" name="ordem" type="number" min="0" max="999" value="{{ $viatura->ordem }}"
                                   class="w-full rounded border border-slate-200 px-2 py-1.5 text-slate-950">
                        </td>
                        <td class="p-3 text-center">
                            <input form="viatura-{{ $viatura->id }}" name="ativo" type="checkbox" value="1" @checked($viatura->ativo)
                                   class="rounded border-slate-300">
                        </td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <button form="viatura-{{ $viatura->id }}" class="rounded bg-[#22C55E] px-3 py-1.5 text-xs font-semibold text-[#0A0F1A]">Guardar</button>
                                <form method="post" action="{{ route('viaturas.destroy', $viatura) }}"
                                      onsubmit="return confirm('Remover a viatura {{ $viatura->matricula }}?');">
                                    @csrf
                                    @method('delete')
                                    <button class="rounded border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Remover</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-slate-400">
                            Ainda nao ha viaturas. Adiciona a primeira acima — sem viaturas nao e possivel escolher matricula na preparacao.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        Desativar uma viatura tira-a da lista da preparacao mas mantem-na nas guias ja emitidas. Remover apaga o registo.
    </p>
</x-layouts.app>
