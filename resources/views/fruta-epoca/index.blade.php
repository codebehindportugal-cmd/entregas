<x-layouts.app title="Fruta da epoca">
    <x-page-title title="Fruta da epoca" subtitle="O fruto concreto de cada mes, tal como sai nas guias e nas faturas" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">{{ $labelMesAtual }}</p>
        <p class="mt-1 text-3xl font-semibold text-[#14532d]">{{ $atual }}</p>
        <p class="mt-2 text-sm text-slate-600">
            @if($temOverrideAtual)
                E este o nome que aparece nas guias de transporte e nas faturas emitidas este mes.
            @else
                Ainda nao ha fruta definida para este mes — os documentos saem com o nome generico. Define abaixo.
            @endif
        </p>
    </div>

    <form method="post" action="{{ route('fruta-epoca.store') }}" class="mb-8 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-[#14532d]">Definir fruta da epoca</h2>
        <div class="grid gap-3 lg:grid-cols-[200px_1fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700">Mes
                <input name="periodo" type="month" value="{{ old('periodo', $mesAtual) }}" required
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Fruta
                <input name="nome" type="text" value="{{ old('nome') }}" placeholder="Ex.: Ameixa" required
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                <span class="mt-1 block text-xs text-slate-500">Podem ser varias separadas por virgula (ex.: Ameixa, Uva).</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Referencia Moloni (opcional)
                <input name="referencia" type="text" value="{{ old('referencia') }}" placeholder="Ex.: HM175"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                <span class="mt-1 block text-xs text-slate-500">So se esta fruta tiver artigo proprio no Moloni.</span>
            </label>
            <div class="flex items-end">
                <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Guardar sobre um mes que ja exista substitui o valor anterior. Os documentos ja emitidos nao mudam.</p>
    </form>

    <div class="overflow-hidden rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Mes</th>
                    <th class="p-3">Fruta</th>
                    <th class="p-3">Referencia Moloni</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $linha)
                    <tr class="border-t border-slate-100 {{ $linha['periodo'] === $mesAtual ? 'bg-emerald-50/50' : '' }}">
                        <td class="p-3 font-medium text-slate-800">
                            {{ $linha['label'] }}
                            @if($linha['periodo'] === $mesAtual)
                                <span class="ml-2 rounded bg-[#22C55E] px-2 py-0.5 text-xs font-semibold text-[#0A0F1A]">mes atual</span>
                            @endif
                        </td>
                        <td class="p-3 text-slate-700">{{ $linha['nome'] ?: '—' }}</td>
                        <td class="p-3 text-slate-500">{{ $linha['referencia'] ?: '—' }}</td>
                        <td class="p-3 text-right">
                            <form method="post" action="{{ route('fruta-epoca.destroy') }}" onsubmit="return confirm('Remover a fruta da epoca de {{ $linha['label'] }}?');">
                                @csrf
                                @method('delete')
                                <input type="hidden" name="periodo" value="{{ $linha['periodo'] }}">
                                <button class="rounded border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">Remover</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-slate-500">Ainda nao ha nenhum mes definido.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
