<x-layouts.app title="Editar empresa">
    <x-page-title title="Editar empresa" subtitle="{{ $corporate->empresa }}" />
    <form method="post" action="{{ route('corporates.update', $corporate) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @method('put')
        @include('corporates._form')
        <button class="mt-6 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Atualizar</button>
    </form>
</x-layouts.app>
