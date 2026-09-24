<?php

namespace App\Http\Controllers;

use App\Models\SepaCobranca;
use App\Models\SepaMandato;
use App\Services\SepaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestao -> Debitos SEPA. Clientes que pagam por debito direto (mandatos) e os
 * ficheiros pain.008 que se enviam ao banco, um por cliente e por mes.
 */
class SepaController extends Controller
{
    public function __construct(private readonly SepaService $sepa)
    {
    }

    public function index(): View
    {
        $mandatos = SepaMandato::query()->with('cobrancas')->orderByDesc('ativo')->orderBy('nome_devedor')->get();

        $linhas = $mandatos->map(function (SepaMandato $m): array {
            $data = $this->sepa->dataSugerida($m);
            $pendentes = $this->sepa->mesesPendentes($m, $data);

            return [
                'mandato' => $m,
                'data' => $data,
                'pendentes' => $pendentes,
                'sugeridos' => $this->sepa->mesesSugeridos($m, $data),
                'em_atraso' => count($this->sepa->mesesPendentes($m, now())),
                'numero' => $this->sepa->numeroPedido($m, $data),
            ];
        });

        return view('sepa.index', [
            'linhas' => $linhas,
            'cobrancas' => SepaCobranca::query()->with('mandato')->orderByDesc('data_cobranca')->orderByDesc('id')->limit(100)->get(),
            'credor' => $this->sepa->credor(),
            'primeiraData' => $this->sepa->primeiraDataPossivel(),
            'mesesNome' => fn (string $ym): string => SepaService::nomeMes($ym),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SepaMandato::create($this->validarMandato($request));

        return redirect()->route('sepa.index')->with('status', 'Cliente adicionado aos débitos diretos.');
    }

    public function edit(SepaMandato $mandato): View
    {
        return view('sepa.edit', ['mandato' => $mandato]);
    }

    public function update(Request $request, SepaMandato $mandato): RedirectResponse
    {
        $mandato->update($this->validarMandato($request, $mandato));

        return redirect()->route('sepa.index')->with('status', 'Mandato de '.$mandato->nome_devedor.' guardado.');
    }

    public function gerar(Request $request, SepaMandato $mandato): RedirectResponse
    {
        $data = $request->validate([
            'data_cobranca' => ['required', 'date_format:Y-m-d'],
            'n_meses' => ['required', 'integer', 'min:1', 'max:24'],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'msg_id' => ['required', 'string', 'max:35', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'msg_id.required' => 'Indica o número do pedido (o mesmo que vais pôr no formulário do banco).',
            'msg_id.regex' => 'O número do pedido só pode ter letras, números e hífen.',
        ]);

        try {
            $cobranca = $this->sepa->gerar(
                $mandato,
                Carbon::createFromFormat('Y-m-d', $data['data_cobranca']),
                (int) $data['n_meses'],
                (float) str_replace(',', '.', (string) $data['valor']),
                $request->user()?->name,
                $data['msg_id'],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['sepa' => $e->getMessage()]);
        }

        return redirect()->route('sepa.index')
            ->with('status', 'Ficheiro gerado: '.$mandato->nome_devedor.' — nº do pedido '.$cobranca->msg_id.' — '.$cobranca->descricao.' — '
                .number_format((float) $cobranca->valor, 2, ',', ' ').' € a '.$cobranca->data_cobranca->format('d/m/Y').'.')
            ->with('descarregar', $cobranca->id);
    }

    public function xml(SepaCobranca $cobranca): Response
    {
        return response($cobranca->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$cobranca->nomeFicheiro().'"',
        ]);
    }

    public function destroy(SepaCobranca $cobranca): RedirectResponse
    {
        try {
            $this->sepa->desfazer($cobranca);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sepa' => $e->getMessage()]);
        }

        return redirect()->route('sepa.index')->with('status', 'Cobrança apagada. O último mês cobrado voltou atrás.');
    }

    public function credor(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:70'],
            'iban' => ['required', 'string', 'max:40'],
            'bic' => ['required', 'string', 'max:11'],
            'identificador' => ['required', 'string', 'max:35'],
            'prefixo' => ['required', 'string', 'alpha_num', 'max:6'],
            'descricao' => ['nullable', 'string', 'max:60'],
        ]);

        if (! SepaService::ibanValido($dados['iban'])) {
            return back()->withInput()->withErrors(['iban' => 'O IBAN do credor não é válido.']);
        }
        if (! SepaService::bicValido($dados['bic'])) {
            return back()->withInput()->withErrors(['bic' => 'O BIC do credor não é válido.']);
        }

        $dados['iban'] = SepaService::normalizarIban($dados['iban']);
        $dados['bic'] = strtoupper(trim($dados['bic']));
        $dados['identificador'] = strtoupper(trim($dados['identificador']));
        $dados['prefixo'] = strtoupper($dados['prefixo']);
        $this->sepa->guardarCredor($dados);

        return redirect()->route('sepa.index')->with('status', 'Dados do credor guardados.');
    }

    /** @return array<string,mixed> */
    private function validarMandato(Request $request, ?SepaMandato $mandato = null): array
    {
        $dados = $request->validate([
            'nome_devedor' => ['required', 'string', 'max:70'],
            'nif' => ['nullable', 'string', 'max:20'],
            'iban' => ['required', 'string', 'max:40'],
            'bic' => ['nullable', 'string', 'max:11'],
            'codigo_pedido' => ['nullable', 'string', 'max:10', 'alpha_num'],
            'mandato_ref' => ['required', 'string', 'max:35', Rule::unique('sepa_mandatos', 'mandato_ref')->ignore($mandato?->id)],
            'data_assinatura' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'valor_mensal' => ['required', 'numeric', 'min:0.01'],
            'ultimo_mes_cobrado' => ['required', 'date_format:Y-m'],
            'max_meses_por_cobranca' => ['required', 'integer', 'min:1', 'max:12'],
            'dia_cobranca' => ['required', 'integer', 'min:1', 'max:28'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], [
            'mandato_ref.unique' => 'Já existe um cliente com essa referência de mandato.',
            'ultimo_mes_cobrado.required' => 'Indica o último mês que já foi cobrado (ex.: 2026-05).',
        ]);

        if (! SepaService::ibanValido($dados['iban'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['iban' => 'O IBAN não é válido — confirma se não falta nenhum dígito.']);
        }
        if (filled($dados['bic'] ?? null) && ! SepaService::bicValido($dados['bic'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['bic' => 'O BIC não é válido (8 ou 11 caracteres, ex.: BESCPTPL).']);
        }

        $dados['iban'] = SepaService::normalizarIban($dados['iban']);
        $dados['bic'] = filled($dados['bic'] ?? null) ? strtoupper(trim($dados['bic'])) : null;
        $dados['mandato_ref'] = trim($dados['mandato_ref']);
        $dados['ativo'] = $request->boolean('ativo');

        return $dados;
    }
}
