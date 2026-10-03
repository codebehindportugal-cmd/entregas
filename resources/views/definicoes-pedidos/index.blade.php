<x-layouts.app title="Definições de pedidos">
    <x-page-title title="Definições de pedidos" subtitle="Etiquetas do Gmail e chaves do WhatsApp da caixa de pedidos">
        <a href="{{ route('pedidos-recebidos.index') }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Caixa de pedidos</a>
    </x-page-title>

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-[#14532d]">Token do trabalho do fim do dia</h2>
        <p class="mb-4 mt-1 text-sm text-slate-500">O Claude usa este token para ler o catálogo e os clientes e deixar pedidos na caixa. Não cria encomendas nem faturas. Gerar outro invalida o anterior.</p>

        @if($tokenNovo)
            <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">Copia agora — não volta a aparecer:</p>
                <code class="mt-1 block select-all break-all rounded bg-white px-2 py-1 font-mono text-slate-900">{{ $tokenNovo }}</code>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-slate-600">
                @if($tokenCriadoEm)
                    Token ativo desde {{ \Illuminate\Support\Carbon::parse($tokenCriadoEm)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                @else
                    Sem token.
                @endif
            </span>
            <form method="post" action="{{ route('definicoes-pedidos.token.store') }}" onsubmit="return {{ $tokenCriadoEm ? "confirm('Gerar outro token? O atual deixa de funcionar.')" : 'true' }};">
                @csrf
                <button class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">{{ $tokenCriadoEm ? 'Gerar outro' : 'Gerar token' }}</button>
            </form>
            @if($tokenCriadoEm)
                <form method="post" action="{{ route('definicoes-pedidos.token.destroy') }}" onsubmit="return confirm('Apagar o token? O trabalho do fim do dia deixa de funcionar.');">
                    @csrf
                    @method('delete')
                    <button class="rounded bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200">Apagar</button>
                </form>
            @endif
        </div>
    </div>

    <form method="post" action="{{ route('definicoes-pedidos.update') }}" autocomplete="off">
        @csrf
        @method('put')

        @foreach($esquema as $grupoChave => $grupo)
            <div class="mb-6 rounded border border-emerald-900/10 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#14532d]">{{ $grupo['titulo'] }}</h2>
                @if(! empty($grupo['descricao']))
                    <p class="mb-4 mt-1 text-sm text-slate-500">{{ $grupo['descricao'] }}</p>
                @endif

                @if($grupoChave === 'whatsapp')
                    <div class="mb-4 rounded bg-slate-50 p-3 text-xs text-slate-600">
                        Endereço do webhook a pôr na Meta (Callback URL): <code class="select-all font-semibold text-slate-900">{{ $webhookUrl }}</code><br>
                        Subscrever o campo <code>messages</code>. O verify token tem de ser igual ao que guardares aqui.
                    </div>
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($grupo['campos'] as $chave => $campo)
                        <label class="text-sm font-medium text-slate-700">
                            <span class="flex items-center gap-2">
                                {{ $campo['label'] }}
                                @if($campo['tipo'] === 'segredo')
                                    @if($definidos[$chave] ?? false)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-normal text-emerald-800">definido</span>
                                    @else
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-normal text-slate-400">por definir</span>
                                    @endif
                                @endif
                            </span>

                            @if($campo['tipo'] === 'booleano')
                                <span class="mt-2 flex items-center gap-2">
                                    <input type="hidden" name="{{ $chave }}" value="0">
                                    <input type="checkbox" name="{{ $chave }}" value="1" @checked(filter_var($valores[$chave] ?? false, FILTER_VALIDATE_BOOLEAN))
                                           class="h-4 w-4 rounded border-slate-300">
                                    <span class="text-sm font-normal text-slate-600">Ligado</span>
                                </span>
                            @elseif($campo['tipo'] === 'segredo')
                                <input name="{{ $chave }}" type="password" autocomplete="new-password"
                                       placeholder="{{ ($definidos[$chave] ?? false) ? '•••••••• (deixa em branco para manter)' : '' }}"
                                       class="mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm">
                                @if($definidos[$chave] ?? false)
                                    <span class="mt-1 flex items-center gap-2 text-xs font-normal text-slate-500">
                                        <input type="checkbox" name="apagar[]" value="{{ $chave }}" class="h-3.5 w-3.5 rounded border-slate-300"> Apagar
                                    </span>
                                @endif
                            @else
                                <input name="{{ $chave }}" type="text" value="{{ old($chave, $valores[$chave] ?? '') }}"
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
            <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar definições</button>
            <span class="text-xs text-slate-500">As chaves ficam encriptadas na base de dados e não voltam a aparecer nesta página.</span>
        </div>
    </form>
</x-layouts.app>
