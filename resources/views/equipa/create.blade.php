<x-layouts.app title="Novo colaborador">
    <x-page-title title="Novo colaborador" />
    <form method="post" action="{{ route('equipa.store') }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @include('equipa._form')
        <button class="mt-6 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
    </form>
</x-layouts.app>
