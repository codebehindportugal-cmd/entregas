<x-layouts.app title="Definicoes Moloni">
    <x-page-title title="Definicoes Moloni" subtitle="Series, artigos, IVA e regras de faturacao" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        As credenciais de acesso ao Moloni (utilizador, palavra-passe, client secret e token) continuam so no ficheiro <code>.env</code> do servidor — nao passam por esta pagina nem ficam na base de dados.
        Empresa Moloni em uso: <strong>{{ $companyId ?: 'nao definida' }}</strong>.
    </div>

    <form method="post" action="{{ route('definicoes-moloni.update') }}">
        @csrf
        @method('put')

        @foreach($esquema as $grupoChave => $grupo)
            <div class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-[#14532d]">{{ $grupo['titulo'] }}</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($grupo['campos'] as $chave => $campo)
                        <label class="text-sm font-medium text-slate-700 {{ $campo['tipo'] === 'textarea' ? 'lg:col-span-2' : '' }}">
                            <span class="flex items-center gap-2">
                                {{ $campo['label'] }}
                                @if($origens[$chave] === 'env')
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-normal text-slate-500" title="Valor a vir do .env; se guardares aqui passa a mandar esta pagina">.env</span>
                                @elseif($origens[$chave] === 'vazio')
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-normal text-slate-400">por definir</span>
                                @endif
                            </span>

                            @if($campo['tipo'] === 'booleano')
                                <span class="mt-2 flex items-center gap-2">
                                    <input type="hidden" name="{{ $chave }}" value="0">
                                    <input type="checkbox" name="{{ $chave }}" value="1" @checked(filter_var($valores[$chave] ?? false, FILTER_VALIDATE_BOOLEAN))
                                           class="h-4 w-4 rounded border-slate-300">
                                    <span class="text-sm font-normal text-slate-600">Ligado</span>
                                </span>
                            @elseif($campo['tipo'] === 'textarea')
                                <textarea name="{{ $chave }}" rows="3"
                                          class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">{{ old($chave, $valores[$chave]) }}</textarea>
                            @else
                                <input name="{{ $chave }}" type="{{ in_array($campo['tipo'], ['numero', 'decimal'], true) ? 'number' : 'text' }}"
                                       @if($campo['tipo'] === 'decimal') step="0.01" @endif
                                       value="{{ old($chave, $valores[$chave]) }}"
                                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                            @endif

                            @if(! empty($campo['ajuda']))
                                <span class="mt-1 block text-xs font-normal text-slate-500">{{ $campo['ajuda'] }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar definicoes</button>
            <span class="text-xs text-slate-500">Deixar um campo vazio faz voltar ao valor do .env.</span>
        </div>
    </form>
</x-layouts.app>
