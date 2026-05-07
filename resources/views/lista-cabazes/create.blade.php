<x-layouts.app title="Nova lista semanal">
    <x-page-title title="Nova lista semanal" />

    <form method="post" action="{{ route('lista-cabazes.store') }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm text-slate-300">Semana do mes
                <select name="semana_numero" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($semanas as $semana)
                        <option value="{{ $semana }}" @selected(old('semana_numero') == $semana)>Semana {{ $semana }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Mes
                <select name="mes" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($meses as $numero => $mes)
                        <option value="{{ $numero }}" @selected((int) old('mes', now()->month) === $numero)>{{ $mes }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Ano
                <select name="ano" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    @foreach($anos as $ano)
                        <option value="{{ $ano }}" @selected((int) old('ano', now()->year) === $ano)>{{ $ano }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-300">Descricao
                <input name="descricao" value="{{ old('descricao') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
        </div>

        <button class="mt-5 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Criar lista</button>
    </form>
</x-layouts.app>
