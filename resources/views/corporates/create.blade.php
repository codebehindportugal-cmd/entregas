<x-layouts.app title="Nova empresa">
    <x-page-title title="Nova empresa" />
    <form method="post" action="{{ route('corporates.store') }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @include('corporates._form')
        <button class="mt-6 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar</button>
    </form>
</x-layouts.app>
