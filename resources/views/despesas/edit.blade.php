<x-layouts.app title="Editar despesa">
    <x-page-title title="Editar despesa" subtitle="{{ $despesa->titulo }}" />

    <form method="post" action="{{ route('despesas.update', $despesa) }}" enctype="multipart/form-data"
        class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @method('patch')
        @include('despesas._form')
        <div class="flex gap-3">
            <button type="submit"
                class="rounded bg-emerald-500 px-6 py-2 font-semibold text-white hover:bg-emerald-600">Guardar alteracoes</button>
            <a href="{{ route('despesas.index') }}"
                class="rounded border border-white/10 px-6 py-2 text-sm text-slate-300 hover:bg-white/10">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
