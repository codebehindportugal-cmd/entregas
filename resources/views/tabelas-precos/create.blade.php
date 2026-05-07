<x-layouts.app title="Nova tabela de precos">
    <x-page-title title="Nova tabela de precos" />
    <form method="post" action="{{ route('tabelas-precos.store') }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @include('tabelas-precos._form')
        <button class="mt-5 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Criar</button>
    </form>
</x-layouts.app>
