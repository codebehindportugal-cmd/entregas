<x-layouts.app title="Editar mandato SEPA">
    <x-page-title title="Editar mandato SEPA" subtitle="{{ $mandato->nome_devedor }}">
        <a href="{{ route('sepa.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</a>
    </x-page-title>

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('sepa.mandatos.update', $mandato) }}" class="rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        @csrf
        @method('put')
        @include('sepa._mandato_form', ['mandato' => $mandato])
        <div class="mt-5 flex justify-end">
            <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
        </div>
    </form>

    <p class="mt-3 text-xs text-slate-500">
        Mudar o "último mês já cobrado" à mão só é preciso para acertos (ex.: um débito enviado fora da app).
        Um débito devolvido pelo banco também se acerta aqui, recuando o mês.
    </p>
</x-layouts.app>
