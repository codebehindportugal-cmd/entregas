<x-layouts.app title="Fruta da epoca">
    <x-page-title title="Fruta da epoca" subtitle="As frutas que se estao a entregar, tal como saem nas guias e nas faturas" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Esta semana ({{ $labelSemanaAtual }})</p>
        <p class="mt-1 text-3xl font-semibold text-[#14532d]">{{ $atual }}</p>
        <p class="mt-2 text-sm text-slate-600">
            @if($origemAtual === 'semana')
                Definida para esta semana. E o que sai nas guias desta semana.
            @elseif($origemAtual === 'mes')
                Vem do mes. Se esta semana for diferente, define-a abaixo por semana.
            @else
                Ainda nao ha fruta definida para esta semana nem para este mes — os documentos saem com o nome generico. Define abaixo.
            @endif
        </p>
    </div>

    @php($tipo = old('tipo', 'semana'))
    <form method="post" action="{{ route('fruta-epoca.store') }}" class="mb-8 rounded border border-emerald-900/10 bg-white p-5 shadow-sm" id="form-fruta">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-[#14532d]">Definir fruta da epoca</h2>

        <div class="mb-4 flex gap-4 text-sm text-slate-700">
            <label class="flex items-center gap-2"><input type="radio" name="tipo" value="semana" @checked($tipo === 'semana')> Por semana</label>
            <label class="flex items-center gap-2"><input type="radio" name="tipo" value="mes" @checked($tipo === 'mes')> Por mes</label>
        </div>

        <div class="grid gap-3 lg:grid-cols-[200px_1fr_1fr_auto]">
            <label class="text-sm font-medium text-slate-700" data-campo="semana">Semana
                <input name="semana" type="week" value="{{ old('semana', $semanaAtual) }}"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700" data-campo="mes">Mes
                <input name="periodo" type="month" value="{{ old('periodo', $mesAtual) }}"
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Fruta(s)
                <input name="nome" type="text" value="{{ old('nome') }}" placeholder="Ex.: Ameixa, Uva" required
                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                <span class="mt-1 block text-xs text-slate-500">Varias frutas separadas por virgula. Na guia sai "Fruta da epoca ... — Ameixa e Uva".</span>
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
        <p class="mt-3 text-xs text-slate-500">
            A semana manda sobre o mes. As guias usam a semana da entrega; as faturas juntam as frutas de todas as semanas do ciclo.
            Guardar sobre uma semana/mes que ja exista substitui o valor anterior. Os documentos ja emitidos nao mudam.
        </p>
    </form>

    <div class="overflow-hidden rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Periodo</th>
                    <th class="p-3">Fruta(s)</th>
                    <th class="p-3">Referencia Moloni</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $linha)
                    @php($atualLinha = in_array($linha['periodo'], [$mesAtual, $semanaAtual], true))
                    <tr class="border-t border-slate-100 {{ $atualLinha ? 'bg-emerald-50/50' : '' }}">
                        <td class="p-3 font-medium text-slate-800">
                            {{ $linha['label'] }}
                            @if($linha['tipo'] === 'semana')
                                <span class="ml-2 rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">semana</span>
                            @endif
                            @if($atualLinha)
                                <span class="ml-1 rounded bg-[#22C55E] px-2 py-0.5 text-xs font-semibold text-[#0A0F1A]">atual</span>
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
                    <tr><td colspan="4" class="p-4 text-slate-500">Ainda nao ha nenhuma semana ou mes definido.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
    (() => {
        const form = document.getElementById('form-fruta');
        const atualizar = () => {
            const tipo = form.querySelector('[name=tipo]:checked')?.value || 'semana';
            form.querySelector('[data-campo=semana]').style.display = tipo === 'semana' ? '' : 'none';
            form.querySelector('[data-campo=mes]').style.display = tipo === 'mes' ? '' : 'none';
        };
        form.querySelectorAll('[name=tipo]').forEach(r => r.addEventListener('change', atualizar));
        atualizar();
    })();
    </script>
</x-layouts.app>
