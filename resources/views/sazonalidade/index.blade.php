<x-layouts.app title="Sazonalidade">
    <x-page-title title="Sazonalidade & Cabazes" subtitle="Produtos por época e composição dos cabazes">
    </x-page-title>

    {{-- SAZONALIDADE --}}
    <div class="mb-8">
        <h2 class="mb-3 text-base font-bold text-slate-800">Produtos por época</h2>

        <div class="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3">Produto</th>
                        <th class="p-3">Categoria</th>
                        @foreach($meses as $num => $abrev)
                            <th class="p-2 text-center text-[10px]">{{ $abrev }}</th>
                        @endforeach
                        <th class="p-3">Notas</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produtos as $produto)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="p-3 font-semibold text-slate-900">{{ $produto->produto }}</td>
                            <td class="p-3">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold
                                    @if($produto->categoria === 'fruta') bg-orange-100 text-orange-800
                                    @elseif($produto->categoria === 'legume') bg-green-100 text-green-800
                                    @elseif($produto->categoria === 'hortalica') bg-emerald-100 text-emerald-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ $produto->categoria }}
                                </span>
                            </td>
                            @foreach($meses as $num => $abrev)
                                <td class="p-2 text-center">
                                    @if(in_array($num, $produto->meses ?? []))
                                        <span class="inline-block h-3 w-3 rounded-full bg-emerald-400" title="{{ $abrev }}"></span>
                                    @else
                                        <span class="inline-block h-3 w-3 rounded-full bg-slate-100"></span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="p-3 text-xs text-slate-500">{{ $produto->notas }}</td>
                            <td class="p-3">
                                <form method="post" action="{{ route('sazonalidade.destroy', $produto) }}"
                                      onsubmit="return confirm('Remover {{ $produto->produto }}?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($meses) }}" class="p-6 text-center text-slate-400">
                                Ainda não existem produtos. Adiciona abaixo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Adicionar produto --}}
        <div class="mt-4 rounded border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-slate-700">Adicionar produto</h3>
            <form method="post" action="{{ route('sazonalidade.store') }}" class="space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-[2fr_1fr_1fr]">
                    <input name="produto" placeholder="Nome do produto (ex: Morango)" required
                           class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                    <select name="categoria" required class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                        <option value="">Categoria...</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                    <input name="notas" placeholder="Notas (opcional)"
                           class="rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm">
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold text-slate-600">Meses disponíveis:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($meses as $num => $abrev)
                            <label class="flex cursor-pointer items-center gap-1 rounded border border-slate-200 px-2 py-1 text-xs hover:bg-emerald-50">
                                <input type="checkbox" name="meses[]" value="{{ $num }}" class="rounded border-slate-300 accent-emerald-600">
                                {{ $abrev }}
                            </label>
                        @endforeach
                    </div>
                    @error('meses') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Adicionar produto
                </button>
            </form>
        </div>
    </div>

    {{-- CABAZ TEMPLATES --}}
    <div>
        <h2 class="mb-3 text-base font-bold text-slate-800">Composição dos cabazes (regras por categoria)</h2>
        <p class="mb-3 text-xs text-slate-500">Define quantos produtos de cada categoria entram em cada tipo de cabaz na geração automática.</p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($tipos as $tipo => $label)
                <div class="rounded border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 font-bold text-slate-800">Cabaz {{ $label }}</h3>

                    @php $templatesTipo = $templates->get($tipo, collect()); @endphp

                    @if($templatesTipo->isNotEmpty())
                        <table class="mb-3 w-full text-xs">
                            <thead class="text-slate-500">
                                <tr>
                                    <th class="pb-1 text-left">Categoria</th>
                                    <th class="pb-1 text-center">Nº itens</th>
                                    <th class="pb-1 text-center">Qtd/item</th>
                                    <th class="pb-1 text-center">Un.</th>
                                    <th class="pb-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($templatesTipo as $tpl)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-1 font-semibold">{{ $tpl->categoria }}</td>
                                        <td class="py-1 text-center">{{ $tpl->quantidade_itens }}</td>
                                        <td class="py-1 text-center">{{ $tpl->quantidade_por_item }}</td>
                                        <td class="py-1 text-center">{{ $tpl->unidade }}</td>
                                        <td class="py-1 text-right">
                                            <form method="post" action="{{ route('cabaz-templates.destroy', $tpl) }}" onsubmit="return confirm('Remover regra?')">
                                                @csrf @method('DELETE')
                                                <button class="text-rose-500 hover:text-rose-700">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="mb-3 text-xs text-slate-400">Sem regras definidas.</p>
                    @endif

                    {{-- Add template rule --}}
                    <form method="post" action="{{ route('cabaz-templates.store') }}" class="space-y-2 border-t border-slate-100 pt-3">
                        @csrf
                        <input type="hidden" name="cabaz_tipo" value="{{ $tipo }}">
                        <select name="categoria" required class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-950">
                            <option value="">Categoria...</option>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-3 gap-1">
                            <input name="quantidade_itens" type="number" min="1" placeholder="Nº" required
                                   class="rounded border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-950" title="Número de produtos distintos">
                            <input name="quantidade_por_item" type="number" step="0.001" min="0.001" placeholder="Qtd" required
                                   class="rounded border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-950" title="Quantidade de cada produto">
                            <input name="unidade" placeholder="un" value="un"
                                   class="rounded border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-950" title="Unidade">
                        </div>
                        <button class="w-full rounded bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800">
                            + Adicionar regra
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
