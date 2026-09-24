<x-layouts.app title="Débitos SEPA">
    <x-page-title title="Débitos SEPA" subtitle="Ficheiros de débito direto para enviar ao banco — um por cliente e por mês" />

    @if(session('status'))
        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
            {{ session('status') }}
            @if(session('descarregar'))
                <a href="{{ route('sepa.cobrancas.xml', session('descarregar')) }}" class="ml-2 underline">Descarregar outra vez</a>
            @endif
        </div>
        @if(session('descarregar'))
            <script>window.addEventListener('load', () => { window.location.href = @json(route('sepa.cobrancas.xml', session('descarregar'))); });</script>
        @endif
    @endif

    @if($errors->any())
        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">{{ $errors->first() }}</div>
    @endif

    {{-- ============================================================ Gerar --}}
    <div class="mb-8 overflow-x-auto rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Mandato</th>
                    <th class="p-3 text-right">Por mês</th>
                    <th class="p-3">Último cobrado</th>
                    <th class="p-3">Gerar cobrança</th>
                </tr>
            </thead>
            <tbody>
                @forelse($linhas as $l)
                    @php($m = $l['mandato'])
                    <tr class="border-t border-slate-100 align-top {{ $m->ativo ? '' : 'bg-slate-50 text-slate-400' }}">
                        <td class="p-3">
                            <div class="font-semibold text-slate-900">{{ $m->nome_devedor }}</div>
                            <div class="text-xs text-slate-500">
                                @if($m->nif) NIF {{ $m->nif }} · @endif
                                <span class="font-mono">{{ $m->ibanMascarado() }}</span>
                            </div>
                            <a href="{{ route('sepa.mandatos.edit', $m) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Editar</a>
                        </td>
                        <td class="p-3 text-xs text-slate-600">
                            {{ $m->mandato_ref }}<br>
                            assinado {{ $m->data_assinatura->format('d/m/Y') }}
                        </td>
                        <td class="p-3 text-right font-semibold whitespace-nowrap">{{ number_format((float) $m->valor_mensal, 2, ',', ' ') }} €</td>
                        <td class="p-3">
                            <div>{{ $m->ultimo_mes_cobrado ? $mesesNome($m->ultimo_mes_cobrado) : '—' }}</div>
                            @if($l['em_atraso'] > 1)
                                <span class="mt-1 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $l['em_atraso'] }} meses por cobrar</span>
                            @elseif($l['em_atraso'] === 1)
                                <span class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">1 mês por cobrar</span>
                            @else
                                <span class="mt-1 inline-block rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Em dia</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if(! $m->ativo)
                                <span class="text-xs">Desativado</span>
                            @else
                                <form method="post" action="{{ route('sepa.gerar', $m) }}" class="sepa-gerar flex flex-wrap items-end gap-2"
                                      data-ultimo="{{ $m->ultimo_mes_cobrado }}"
                                      data-valor="{{ (float) $m->valor_mensal }}"
                                      data-max="{{ (int) $m->max_meses_por_cobranca }}"
                                      data-codigo="{{ preg_replace('/[^A-Za-z0-9]/', '', (string) $m->codigo_pedido) }}"
                                      data-meses-usados='@json($m->cobrancas->pluck('mes_cobranca')->values())'
                                      onsubmit="return confirm('Gerar o ficheiro nº ' + this.querySelector('[name=msg_id]').value + ' de ' + this.querySelector('[data-resumo]').textContent + '?');">
                                    @csrf
                                    <label class="text-xs font-medium text-slate-600">Data de cobrança
                                        <input name="data_cobranca" type="date" required
                                               min="{{ $primeiraData->format('Y-m-d') }}"
                                               value="{{ $l['data']->format('Y-m-d') }}"
                                               class="mt-1 block rounded border border-slate-200 px-2 py-1.5 text-slate-950">
                                    </label>
                                    <label class="text-xs font-medium text-slate-600">Meses
                                        <select name="n_meses" class="mt-1 block rounded border border-slate-200 px-2 py-1.5 text-slate-950">
                                            @foreach(range(1, max(1, count($l['pendentes']))) as $n)
                                                <option value="{{ $n }}" @selected($n === $l['sugeridos'])>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <input type="hidden" name="form_mandato" value="{{ $m->id }}">
                                    <label class="text-xs font-medium text-slate-600">Nº do pedido
                                        <input name="msg_id" type="text" required maxlength="35" pattern="[A-Za-z0-9\-]+" inputmode="numeric"
                                               value="{{ (string) old('form_mandato') === (string) $m->id ? old('msg_id') : $l['numero'] }}"
                                               placeholder="Ex.: 26252305"
                                               class="mt-1 block w-32 rounded border border-slate-200 px-2 py-1.5 font-mono text-slate-950">
                                    </label>
                                    <label class="text-xs font-medium text-slate-600">Valor (€)
                                        <input name="valor" type="number" step="0.01" min="0.01" required
                                               value="{{ number_format($l['sugeridos'] * (float) $m->valor_mensal, 2, '.', '') }}"
                                               class="mt-1 block w-28 rounded border border-slate-200 px-2 py-1.5 text-right text-slate-950">
                                    </label>
                                    <button class="mb-0.5 rounded bg-[#22C55E] px-3 py-1.5 text-xs font-semibold text-[#0A0F1A] disabled:opacity-40">Gerar ficheiro</button>
                                    <p class="w-full text-xs text-slate-500" data-resumo></p>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-slate-400">Ainda não há clientes com débito direto. Adiciona o primeiro abaixo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============================================================ Historico --}}
    <h2 class="mb-3 text-lg font-semibold text-[#14532d]">Ficheiros gerados</h2>
    <div class="mb-8 overflow-x-auto rounded border border-emerald-900/10 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-emerald-50 text-slate-700">
                <tr>
                    <th class="p-3">Cobrança</th>
                    <th class="p-3">Nº do pedido</th>
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Meses</th>
                    <th class="p-3 text-right">Valor</th>
                    <th class="p-3">Gerado</th>
                    <th class="p-3">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cobrancas as $c)
                    <tr class="border-t border-slate-100">
                        <td class="p-3 whitespace-nowrap font-semibold">{{ $c->data_cobranca->format('d/m/Y') }}</td>
                        <td class="p-3 font-mono font-semibold text-slate-900">{{ $c->msg_id }}</td>
                        <td class="p-3">{{ $c->mandato?->nome_devedor }}</td>
                        <td class="p-3">{{ $c->descricao }}</td>
                        <td class="p-3 text-right whitespace-nowrap">{{ number_format((float) $c->valor, 2, ',', ' ') }} €</td>
                        <td class="p-3 text-xs text-slate-500">{{ $c->created_at->format('d/m/Y H:i') }}@if($c->gerado_por) · {{ $c->gerado_por }}@endif</td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <a href="{{ route('sepa.cobrancas.xml', $c) }}" class="rounded border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50">XML</a>
                                <form method="post" action="{{ route('sepa.cobrancas.destroy', $c) }}"
                                      onsubmit="return confirm('Apagar esta cobrança? Só o faças se o ficheiro NÃO foi enviado ao banco.');">
                                    @csrf
                                    @method('delete')
                                    <button class="rounded border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Apagar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-slate-400">Ainda não foi gerado nenhum ficheiro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ============================================================ Novo cliente --}}
    <details class="mb-6 rounded border border-emerald-900/10 bg-white shadow-sm" @if($errors->any() && old('mandato_ref')) open @endif>
        <summary class="cursor-pointer p-5 text-lg font-semibold text-[#14532d]">Adicionar cliente com débito direto</summary>
        <form method="post" action="{{ route('sepa.mandatos.store') }}" class="border-t border-slate-100 p-5">
            @csrf
            @include('sepa._mandato_form', ['mandato' => null])
            <div class="mt-5 flex justify-end">
                <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Adicionar</button>
            </div>
        </form>
    </details>

    {{-- ============================================================ Credor --}}
    <details class="rounded border border-emerald-900/10 bg-white shadow-sm" @if($errors->has('iban') && old('identificador')) open @endif>
        <summary class="cursor-pointer p-5 text-lg font-semibold text-[#14532d]">Dados do credor (quem recebe)</summary>
        <form method="post" action="{{ route('sepa.credor') }}" class="grid gap-4 border-t border-slate-100 p-5 md:grid-cols-3">
            @csrf
            @method('put')
            @php($in = 'mt-1 w-full rounded border border-slate-200 bg-white px-3 py-2 text-slate-950 shadow-sm')
            <label class="text-sm font-medium text-slate-700">Nome
                <input name="nome" value="{{ old('nome', $credor['nome']) }}" required maxlength="70" class="{{ $in }}">
            </label>
            <label class="text-sm font-medium text-slate-700">IBAN
                <input name="iban" value="{{ old('iban', $credor['iban']) }}" required class="{{ $in }} font-mono">
            </label>
            <label class="text-sm font-medium text-slate-700">BIC
                <input name="bic" value="{{ old('bic', $credor['bic']) }}" required maxlength="11" class="{{ $in }} font-mono">
            </label>
            <label class="text-sm font-medium text-slate-700">Identificador de credor SEPA
                <input name="identificador" value="{{ old('identificador', $credor['identificador']) }}" required maxlength="35" class="{{ $in }} font-mono">
            </label>
            <label class="text-sm font-medium text-slate-700">Prefixo dos ficheiros
                <input name="prefixo" value="{{ old('prefixo', $credor['prefixo']) }}" required maxlength="6" class="{{ $in }} font-mono uppercase">
                <span class="mt-1 block text-xs text-slate-500">Início do identificador de cada ficheiro (ex.: ATN202610…).</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Texto no extrato do cliente
                <input name="descricao" value="{{ old('descricao', $credor['descricao']) }}" maxlength="60" class="{{ $in }}">
                <span class="mt-1 block text-xs text-slate-500">Seguido dos meses: "Horta da Maria - Junho e Julho 2026".</span>
            </label>
            <div class="md:col-span-3 flex justify-end">
                <button class="rounded bg-[#22C55E] px-5 py-2 font-semibold text-[#0A0F1A]">Guardar credor</button>
            </div>
        </form>
    </details>

    <p class="mt-4 text-xs text-slate-500">
        Cobra-se sempre em atraso: numa cobrança de outubro entram meses até setembro. Com meses em atraso, cada cobrança leva
        até ao máximo definido no cliente (2 por defeito) até ficar em dia. O banco precisa do ficheiro pelo menos um dia útil antes da data de cobrança.
    </p>

    <script>
    (() => {
        const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        const addMes = (ym, n) => { let [a, m] = ym.split('-').map(Number); m += n; a += Math.floor((m - 1) / 12); m = ((m - 1) % 12 + 12) % 12 + 1; return a + '-' + String(m).padStart(2, '0'); };
        const nome = ym => { const [a, m] = ym.split('-').map(Number); return MESES[m - 1] + ' ' + a; };
        const descrever = (i, f) => {
            if (i === f) return nome(i);
            const [ai, mi] = i.split('-').map(Number), [af, mf] = f.split('-').map(Number);
            const n = (af - ai) * 12 + (mf - mi) + 1, lig = n === 2 ? ' e ' : ' a ';
            return ai === af ? MESES[mi - 1] + lig + MESES[mf - 1] + ' ' + af : nome(i) + lig + nome(f);
        };
        const eur = v => v.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

        document.querySelectorAll('form.sepa-gerar').forEach(form => {
            const ultimo = form.dataset.ultimo, mensal = parseFloat(form.dataset.valor), max = parseInt(form.dataset.max, 10);
            const usados = JSON.parse(form.dataset.mesesUsados || '[]');
            const data = form.querySelector('[name=data_cobranca]'), sel = form.querySelector('[name=n_meses]');
            const valor = form.querySelector('[name=valor]'), resumo = form.querySelector('[data-resumo]'), botao = form.querySelector('button');

            const pendentes = () => {
                if (!ultimo || !data.value) return [];
                const limite = addMes(data.value.slice(0, 7), -1), out = [];
                for (let ym = addMes(ultimo, 1); ym <= limite && out.length < 36; ym = addMes(ym, 1)) out.push(ym);
                return out;
            };
            const atualizarResumo = () => {
                const p = pendentes(), n = parseInt(sel.value, 10) || 0;
                const mesCob = data.value.slice(0, 7);
                if (usados.includes(mesCob)) { resumo.textContent = 'Já há uma cobrança neste mês — o banco só aceita uma.'; botao.disabled = true; return; }
                if (!p.length) { resumo.textContent = 'Nada por cobrar nesta data (cobra-se o mês anterior).'; botao.disabled = true; return; }
                botao.disabled = false;
                resumo.textContent = descrever(p[0], p[n - 1]) + ' — ' + eur(parseFloat(valor.value || 0))
                    + (p.length > n ? ' · ficam ' + (p.length - n) + ' mês(es) para a próxima' : ' · fica em dia');
            };
            const atualizarMeses = (repor) => {
                const p = pendentes(), atual = parseInt(sel.value, 10);
                sel.innerHTML = '';
                for (let n = 1; n <= Math.max(1, p.length); n++) sel.add(new Option(n, n));
                sel.value = repor ? Math.min(p.length || 1, max) : Math.min(atual || 1, p.length || 1);
                valor.value = (mensal * parseInt(sel.value, 10)).toFixed(2);
                atualizarResumo();
            };

            const numero = form.querySelector('[name=msg_id]'), codigo = form.dataset.codigo || '';
            const atualizarNumero = () => {
                if (!codigo || !data.value) return;
                const [a, m, d] = data.value.split('-');
                numero.value = codigo + a.slice(2) + d + m;
            };

            data.addEventListener('change', () => { atualizarNumero(); atualizarMeses(true); });
            sel.addEventListener('change', () => { valor.value = (mensal * parseInt(sel.value, 10)).toFixed(2); atualizarResumo(); });
            valor.addEventListener('input', atualizarResumo);
            atualizarMeses(false);
        });
    })();
    </script>
</x-layouts.app>
