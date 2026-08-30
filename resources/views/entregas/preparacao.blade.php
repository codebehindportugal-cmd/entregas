@php
    $labels = [
        'banana' => 'Bananas',
        'maca' => 'Macas',
        'pera' => 'Peras',
        'laranja' => 'Laranjas',
        'uvas' => 'Uvas',
        'fruta_epoca' => 'Fruta epoca',
        'frutos_secos' => 'Frutos secos',
        'mirtilos' => 'Mirtilos',
        'framboesas' => 'Framboesas',
        'amoras' => 'Amoras',
        'morangos' => 'Morangos',
    ];
    $produtosKg = \App\Services\ComprasService::PRODUTOS_KG;
@endphp

<x-layouts.app title="Preparacao">
    <x-page-title title="Preparacao" subtitle="Quantidades para {{ $dia }} - {{ $periodoLabel }}" />

    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 lg:grid-cols-[1fr_1fr_1fr_2fr_auto_auto]">
        <label class="text-sm text-slate-300">Inicio
            <input name="inicio" type="date" value="{{ $inicio }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Fim
            <input name="fim" type="date" value="{{ $fim }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Dia
            <select name="dia" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="" @selected($diaFiltro === '')>Todos</option>
                @foreach($dias as $diaOption)
                    <option value="{{ $diaOption }}" @selected($diaFiltro === $diaOption)>{{ $diaOption }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">Pesquisar empresa
            <input name="q" value="{{ $q }}" placeholder="Empresa, sucursal ou morada..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="flex items-center gap-2 self-end text-xs text-slate-300" title="Mostra tambem o que nao tem colaborador atribuido e o que foi dado como nao entregue">
            <input type="checkbox" name="mostrar_tudo" value="1" @checked($mostrarTudo) class="rounded border-white/20 bg-[#0A0F1A]">
            Mostrar tudo
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Ver preparacao</button>
            <a href="{{ route('preparacao.index', ['inicio' => $inicio, 'fim' => $fim]) }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>

    @if(! $mostrarTudo && ($escondidasSemColaborador > 0 || $escondidasNaoEntregues > 0))
        <div class="mb-4 rounded border border-amber-400/30 bg-[#F59E0B]/10 px-4 py-3 text-sm text-amber-100">
            Escondidas
            @if($escondidasSemColaborador > 0)
                <strong>{{ $escondidasSemColaborador }}</strong> entrega(s) sem colaborador atribuido
            @endif
            @if($escondidasSemColaborador > 0 && $escondidasNaoEntregues > 0) e @endif
            @if($escondidasNaoEntregues > 0)
                <strong>{{ $escondidasNaoEntregues }}</strong> dada(s) como nao entregues
            @endif
            . Liga "Mostrar tudo" no filtro para as veres.
        </div>
    @endif

    <div class="preparacao-table-scroll max-h-[72vh] overflow-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 z-20 bg-[#1B2638] text-slate-300 shadow-sm shadow-black/20">
                <tr>
                    <th class="p-3">Empresa</th>
                    <th class="p-3">Data</th>
                    <th class="p-3">Caixas</th>
                    @foreach($labels as $label)
                        <th class="p-3">{{ $label }}</th>
                    @endforeach
                    <th class="p-3">Total</th>
                    <th class="p-3">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($corporatePreparacoes as $preparacao)
                    @php
                        $corporate = $preparacao['corporate'];
                        $dataLinha = $preparacao['data'];
                        $diaLinha = $preparacao['dia'];
                        $usarProdutos = $preparacao['usar_produtos'] ?? true;
                        $frutasEmpresa = $usarProdutos ? $corporate->frutasParaDia($diaLinha) : [];
                        $totalEmpresa = collect(array_keys($labels))->reject(fn (string $key) => in_array($key, $produtosKg, true))->sum(fn (string $key) => (int) ($frutasEmpresa[$key] ?? 0));
                        $item = $preparacaoItems->get('corporate-'.$corporate->id.'-'.$dataLinha);
                        $anchor = 'prep-corporate-'.$corporate->id.'-'.$dataLinha;
                        $tipoEntrega = $preparacao['tipo_entrega'] ?? 'Entrega regular';

                        // Pecas mesmo entregues neste dia contra as do costume.
                        // Quando diferem, a linha ganha cor e mostra as duas.
                        $caixasEmpresa = (int) $corporate->numero_caixas;
                        $pecasEntregues = (int) ($preparacao['pecas_entregues'] ?? $totalEmpresa);
                        $semEntrega = str_contains($tipoEntrega, 'Nao entregamos');
                        $diferenca = $pecasEntregues - $totalEmpresa;
                        $classeLinha = $semEntrega
                            ? 'linha-sem-entrega'
                            : ($diferenca < 0 ? 'linha-reducao' : ($diferenca > 0 ? 'linha-aumento' : ''));
                    @endphp
                    <tr id="{{ $anchor }}" class="scroll-mt-28 border-t border-white/10 {{ $classeLinha }}">
                        <td class="p-3">
                            <p class="font-semibold text-white">{{ $corporate->empresa }}</p>
                            <p class="text-xs text-slate-400">{{ $corporate->sucursal ?: $corporate->moradaParaEntrega() }}</p>
                        </td>
                        <td class="p-3 text-slate-300">
                            <p>{{ \Illuminate\Support\Carbon::parse($dataLinha)->format('d/m/Y') }}</p>
                            <p class="text-xs text-slate-500">{{ $diaLinha }}</p>
                        </td>
                        <td class="p-3 font-semibold text-emerald-200">{{ $corporate->numero_caixas }}</td>
                        @foreach(array_keys($labels) as $key)
                            @php
                                $emKg = in_array($key, $produtosKg, true);
                                $valorProduto = (float) ($frutasEmpresa[$key] ?? 0);
                                // Com mais do que uma caixa, quem prepara precisa de
                                // saber quanto vai para cada uma, nao so o total.
                                // Nas pecas arredonda-se sempre para cima: e melhor
                                // levar uma a mais numa caixa do que faltar numa.
                                $porCaixa = $caixasEmpresa > 1 && $valorProduto > 0
                                    ? $valorProduto / $caixasEmpresa
                                    : null;
                            @endphp
                            <td class="whitespace-nowrap p-3 text-slate-300">
                                {{ $emKg ? number_format($valorProduto, 2, ',', ' ').' kg' : (int) $valorProduto }}
                                @if($porCaixa !== null)
                                    <span class="block text-xs text-slate-500">
                                        {{ $emKg ? number_format($porCaixa, 2, ',', ' ').' kg' : (int) ceil($porCaixa) }}/caixa
                                    </span>
                                @endif
                            </td>
                        @endforeach
                        <td class="whitespace-nowrap p-3 font-semibold text-white">
                            @if($semEntrega)
                                <span class="text-red-700">0</span>
                                <span class="block whitespace-nowrap text-xs font-semibold text-red-700">Nao entregue (habitual {{ $totalEmpresa }})</span>
                            @elseif($diferenca !== 0)
                                {{ $pecasEntregues }}
                                <span class="block whitespace-nowrap text-xs font-semibold {{ $diferenca > 0 ? 'text-blue-700' : 'text-amber-700' }}">
                                    {{ $diferenca > 0 ? '+' : '' }}{{ $diferenca }} (habitual {{ $totalEmpresa }})
                                </span>
                            @else
                                {{ $totalEmpresa }}
                            @endif
                        </td>
                        <td class="p-3">
                            @if($item?->feito)
                                <div class="mb-2 text-xs text-emerald-200">Feito {{ $item->feito_at?->format('H:i') }}</div>
                                <form method="post" action="{{ route('preparacao.update', $item) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="anchor" value="{{ $anchor }}">
                                    <input type="hidden" name="feito" value="0">
                                    <button class="rounded bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200">Marcar por fazer</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('preparacao.update', $item) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="anchor" value="{{ $anchor }}">
                                    <input type="hidden" name="feito" value="1">
                                    @php
                                        // A lista vem do controlador; se a linha tiver uma
                                        // matricula de um carro entretanto desativado, essa
                                        // e acrescentada para nao se perder o valor.
                                        $viaturasLinha = $viaturas;

                                        if (filled($item?->matricula) && $viaturas->doesntContain('matricula', $item->matricula)) {
                                            $viaturasLinha = $viaturas->concat([new \App\Models\Viatura(['matricula' => $item->matricula])]);
                                        }
                                    @endphp
                                    @if($viaturasLinha->isEmpty())
                                        <a href="{{ route('viaturas.index') }}" class="mb-2 block text-xs font-semibold text-amber-700 underline">Sem viaturas — adiciona uma</a>
                                    @else
                                        <select name="matricula" class="mb-2 w-32 rounded border border-white/10 bg-[#151E2D] px-2 py-1 text-xs text-white">
                                            <option value="">Matricula...</option>
                                            @foreach($viaturasLinha as $viatura)
                                                <option value="{{ $viatura->matricula }}" @selected(old('matricula', $item?->matricula) === $viatura->matricula)>{{ $viatura->etiqueta() }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button class="rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">Marcar feito</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($labels) + 5 }}" class="p-4 text-slate-400">Nao existem empresas com entrega neste periodo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="preparacao-table-scroll mt-6 max-h-[72vh] overflow-auto rounded border border-white/10 bg-[#151E2D]">
        <div class="border-b border-white/10 p-4">
            <h2 class="text-lg font-semibold text-white">Clientes B2C</h2>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 z-20 bg-[#1B2638] text-slate-300 shadow-sm shadow-black/20">
                <tr>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Data</th>
                    <th class="p-3">Tipo</th>
                    <th class="p-3">Produtos</th>
                    <th class="p-3">Preferencias</th>
                    <th class="p-3">Produtos no cabaz</th>
                </tr>
            </thead>
            <tbody>
                @forelse($b2cPreparacoes as $preparacao)
                    @php
                        $order = $preparacao['order'];
                        $dataLinha = $preparacao['data'];
                        $diaLinha = $preparacao['dia'];
                        $item = $preparacaoItems->get('b2c-'.$order->id.'-'.$dataLinha);
                        $anchor = 'prep-b2c-'.$order->id.'-'.$dataLinha;
                        $picagem = $preparacao['picagem'] ?? ['tipo' => null, 'lista' => null, 'linhas' => []];
                        $linhasPicagem = $picagem['linhas'];
                        $picados = $item->produtos_picados ?? [];
                    @endphp
                    <tr id="{{ $anchor }}" class="scroll-mt-28 border-t border-white/10 align-top">
                        <td class="p-3">
                            <a href="{{ route('encomendas.show', $order) }}" class="font-semibold text-white hover:text-[#22C55E]">#{{ $order->woo_id }} {{ $order->billing_name ?: 'Sem nome' }}</a>
                            <p class="text-xs text-slate-400">{{ $order->billing_phone ?: $order->billing_email }}</p>
                        </td>
                        <td class="p-3 text-slate-300">
                            <p>{{ \Illuminate\Support\Carbon::parse($dataLinha)->format('d/m/Y') }}</p>
                            <p class="text-xs text-slate-500">{{ $diaLinha }}</p>
                        </td>
                        <td class="p-3 text-slate-300">{{ $order->source_type === 'subscription' ? 'Subscricao' : 'Em processamento' }}</td>
                        <td class="p-3 text-slate-300">
                            @forelse($order->line_items ?? [] as $produto)
                                <p>{{ $produto['quantity'] ?? 0 }}x {{ $produto['name'] ?? 'Produto' }}</p>
                            @empty
                                <span class="text-slate-500">Sem produtos</span>
                            @endforelse
                            @if($picagem['lista'])
                                <p class="mt-1 text-xs text-slate-500">Lista: {{ $picagem['lista']->tituloFormatado() }}</p>
                            @elseif($picagem['tipo'])
                                <p class="mt-1 text-xs text-amber-300">Sem lista publicada para o cabaz {{ $picagem['tipo'] }}.</p>
                            @endif
                        </td>
                        <td class="p-3 text-slate-300">
                            @if($order->preferences_text)
                                <span class="whitespace-pre-line">{{ $order->preferences_text }}</span>
                            @else
                                {{ $order->excluded_products ? implode(', ', $order->excluded_products) : 'Sem exclusoes' }}
                            @endif
                        </td>
                        <td class="p-3">
                            <form method="post" action="{{ route('preparacao.produtos.update', $item) }}" class="min-w-64 space-y-2">
                                @csrf
                                @method('put')
                                <input type="hidden" name="anchor" value="{{ $anchor }}">
                                @forelse($linhasPicagem as $linha)
                                    <label class="flex items-start gap-2 rounded border p-2 text-xs {{ $linha['excluido'] ? 'border-amber-500 bg-amber-100 !text-amber-900 line-through decoration-amber-700/60' : 'border-white/10 bg-[#0A0F1A] text-slate-200' }}">
                                        <input name="produtos_picados[]" type="checkbox" value="{{ $linha['chave'] }}" @checked(in_array($linha['chave'], $picados, true)) class="mt-0.5 rounded border-white/10 bg-[#0A0F1A]">
                                        <span>
                                            {{ $linha['texto'] }}
                                            @if($linha['excluido'])
                                                <span class="ml-1 font-bold no-underline">— cliente excluiu</span>
                                            @endif
                                            @if($linha['origem'] === 'encomenda')
                                                <span class="ml-1 text-[#22C55E]">extra</span>
                                            @endif
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-500">Sem produtos para picar.</p>
                                @endforelse
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <button class="rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">{{ count($linhasPicagem) === 0 ? 'Marcar feito' : 'Guardar' }}</button>
                                    @if($item?->feito)
                                        <span class="text-xs text-emerald-200">Feito {{ $item->feito_at?->format('H:i') }}</span>
                                    @else
                                        <span class="text-xs text-amber-200">{{ count($picados) }}/{{ count($linhasPicagem) }} picados</span>
                                    @endif
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-4 text-slate-400">Nao existem encomendas B2C para esta preparacao.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Uma linha "ativa" de cada vez, em toda a pagina: e a que se esta
            // a preparar. Clicar nela seleciona; marcar feito salta para a
            // seguinte, para nao ser preciso procurar onde se ia.
            const linhas = () => Array.from(document.querySelectorAll('tbody tr[id^="prep-"]'));

            const selecionar = (linha, opcoes = {}) => {
                if (!linha) {
                    return;
                }

                linhas().forEach((outra) => outra.classList.remove('linha-ativa'));
                linha.classList.add('linha-ativa');

                if (opcoes.scroll) {
                    linha.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }

                if (opcoes.focar) {
                    // Depois do scroll suave, senao o browser tira-nos o foco.
                    setTimeout(() => {
                        const campo = linha.querySelector('input[type="text"], input[type="checkbox"]');
                        campo?.focus({ preventScroll: true });
                    }, 350);
                }
            };

            document.addEventListener('click', (evento) => {
                const linha = evento.target.closest('tbody tr[id^="prep-"]');

                if (linha) {
                    selecionar(linha);
                }
            });

            document.addEventListener('focusin', (evento) => {
                const linha = evento.target.closest('tbody tr[id^="prep-"]');

                if (linha) {
                    selecionar(linha);
                }
            });

            // Depois de gravar, o servidor devolve-nos a ancora da linha que
            // acabou de ser feita — a seguir a essa e a que interessa agora.
            const ancora = window.location.hash ? document.querySelector(window.location.hash) : null;

            if (ancora && ancora.matches('tr[id^="prep-"]')) {
                const todas = linhas();
                const seguinte = todas[todas.indexOf(ancora) + 1] ?? ancora;

                selecionar(seguinte, { scroll: true, focar: true });
            }
        });
    </script>
</x-layouts.app>
