<x-layouts.app title="Faturas">
    <x-page-title title="Faturas" subtitle="Documentos emitidos no Moloni a partir da aplicacao" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <form method="get" class="mb-6 grid gap-3 rounded border border-emerald-900/10 bg-white p-4 shadow-sm lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
        <label class="text-sm font-medium text-slate-700">Pesquisar
            <input name="q" type="text" value="{{ $q }}" placeholder="Empresa, NIF, referencia ou nr. do documento"
                   class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
        </label>
        <label class="text-sm font-medium text-slate-700">De
            <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
        </label>
        <label class="text-sm font-medium text-slate-700">Ate
            <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
        </label>
        <label class="text-sm font-medium text-slate-700">Estado
            <select name="estado" class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                <option value="">Todas</option>
                <option value="por_enviar" @selected($estado === 'por_enviar')>Por enviar</option>
                <option value="enviadas" @selected($estado === 'enviadas')>Enviadas</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('faturas.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpar</a>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Faturas</p>
            <p class="mt-1 text-3xl font-semibold text-[#14532d]">{{ $numero }}</p>
        </div>
        <div class="rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total faturado</p>
            <p class="mt-1 text-3xl font-semibold text-[#14532d]">{{ number_format($somaTotal, 2, ',', ' ') }} EUR</p>
        </div>
        <div class="rounded border p-5 shadow-sm {{ $porEnviar > 0 ? 'border-amber-300 bg-amber-50' : 'border-emerald-900/10 bg-white' }}">
            <p class="text-sm {{ $porEnviar > 0 ? 'text-amber-800' : 'text-slate-500' }}">Por enviar ao cliente</p>
            <p class="mt-1 text-3xl font-semibold {{ $porEnviar > 0 ? 'text-amber-900' : 'text-[#14532d]' }}">{{ $porEnviar }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Emitida</th>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Sucursais</th>
                    <th class="p-3">Ciclo</th>
                    <th class="p-3">Ref. cliente</th>
                    <th class="p-3 text-right">Total</th>
                    <th class="p-3">Documento</th>
                    <th class="p-3">Enviada</th>
                </tr>
            </thead>
            <tbody>
                @forelse($faturas as $fatura)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="p-3 whitespace-nowrap text-slate-700">
                            {{ optional($fatura->emitida_em)->format('d/m/Y H:i') ?: '—' }}
                            @if($fatura->tipo && $fatura->tipo !== 'fatura')
                                <span class="mt-1 block text-xs text-slate-400">{{ $fatura->tipo }}</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <span class="font-medium text-slate-800">{{ $fatura->nome ?: '—' }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $fatura->nif ?: 'sem NIF' }}</span>
                        </td>
                        <td class="p-3 text-xs text-slate-600">
                            @foreach((array) ($fatura->corporate_ids ?? []) as $id)
                                <span class="block">{{ $empresas[$id] ?? ('#'.$id) }}</span>
                            @endforeach
                        </td>
                        <td class="p-3 whitespace-nowrap text-slate-600">{{ $fatura->ciclo_label ?: $fatura->periodo }}</td>
                        <td class="p-3 text-slate-600">{{ $fatura->referencia_cliente ?: '—' }}</td>
                        <td class="p-3 text-right font-medium text-slate-800">{{ number_format((float) $fatura->total, 2, ',', ' ') }}</td>
                        <td class="p-3 whitespace-nowrap">
                            <span class="block text-xs text-slate-500">#{{ $fatura->document_id }}</span>
                            <a href="{{ route('faturas.pdf', $fatura) }}" target="_blank" rel="noopener"
                               class="mt-1 inline-block rounded bg-[#3B82F6] px-3 py-1 text-xs font-semibold text-white">Ver PDF</a>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            <form method="post" action="{{ route('faturas.enviada', $fatura) }}">
                                @csrf
                                @if($fatura->enviada_em)
                                    <span class="block text-xs font-semibold text-emerald-700">Enviada {{ $fatura->enviada_em->format('d/m/Y') }}</span>
                                    @if($fatura->enviada_por)
                                        <span class="block text-xs text-slate-400">por {{ $fatura->enviada_por }}</span>
                                    @endif
                                    <button class="mt-1 rounded border border-slate-200 px-2 py-1 text-xs text-slate-500 hover:bg-slate-50">Desmarcar</button>
                                @else
                                    <button class="rounded bg-[#22C55E] px-3 py-1 text-xs font-semibold text-[#0A0F1A]">Marcar enviada</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4 text-slate-500">Ainda nao ha faturas emitidas pela aplicacao.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $faturas->links() }}</div>
</x-layouts.app>
