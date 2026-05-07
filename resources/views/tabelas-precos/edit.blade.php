<x-layouts.app title="Editar tabela de precos">
    <x-page-title title="Editar tabela de precos" subtitle="{{ $tabelaPreco->fornecedor }}" />
    <form method="post" action="{{ route('tabelas-precos.update', $tabelaPreco) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
        @csrf
        @method('put')
        @include('tabelas-precos._form')
        <button class="mt-5 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Atualizar</button>
    </form>
</x-layouts.app>
