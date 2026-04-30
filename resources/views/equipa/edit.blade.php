<x-layouts.app title="Editar colaborador">
    <x-page-title title="Editar colaborador" subtitle="{{ $user->name }}" />
    <form method="post" action="{{ route('equipa.update', $user) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @method('put')
        @include('equipa._form')
        <button class="mt-6 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Atualizar</button>
    </form>
</x-layouts.app>
