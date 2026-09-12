<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFaturaApiRequest;
use App\Http\Requests\Api\StoreFaturasLoteApiRequest;
use App\Models\Despesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ingestao de faturas de compra pela API — /api/v1/faturas.
 *
 * A via das faturas de papel: quem as le (o chat) manda os dados ja lidos e a
 * entrada de produtos entra como Despesa + FaturaItem, a mesma coisa que o ecra
 * de despesas cria. O caminho e a forma da resposta sao iguais aos do
 * agro.codebehind.pt e do gestao.ateneya.com, de proposito — mesmo trabalho,
 * mesma forma, nos tres projectos.
 *
 * A leitura por IA que o ecra faz (extrairIa / AiJob) nao e tocada: continua a
 * ser a via de quem carrega a foto no painel. Aqui a fatura chega ja lida.
 *
 * Autenticacao: ClaudeApiTokenMiddleware (`claude.api_token`) nas rotas.
 */
class FaturaController extends Controller
{
    use RespondeJson;

    public function store(StoreFaturaApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avisos = [];

        $existente = $this->jaRegistada($data);

        if ($existente !== null) {
            $avisos[] = "fatura ja registada ({$data['numero_fatura']})";

            return $this->criado($this->formatar($existente->load('items')), $avisos);
        }

        try {
            [$despesa, $avisosCriacao] = $this->registar($data);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        return $this->criado($this->formatar($despesa), array_merge($avisos, $avisosCriacao));
    }

    /**
     * Varias faturas num pedido — a pilha de papel fotografada de seguida.
     *
     * Cada uma e validada e gravada por si, em transaccao propria: uma que falhe
     * devolve o erro dela e as outras entram. 201 quando tudo passou, 207 quando
     * o lote foi parcial, 422 quando nao entrou nada.
     */
    public function lote(StoreFaturasLoteApiRequest $request): JsonResponse
    {
        // input() e nao validated(): o pedido so valida o envelope e cada fatura
        // e validada em baixo com as regras completas.
        $faturas = (array) $request->input('faturas', []);

        $resultados = [];
        $erros = [];
        $registadas = 0;
        $repetidas = 0;
        $falhadas = 0;

        foreach (array_values($faturas) as $indice => $fatura) {
            $referencia = is_array($fatura) ? ($fatura['numero_fatura'] ?? null) : null;

            $validador = ValidatorFacade::make(
                is_array($fatura) ? $fatura : [],
                StoreFaturaApiRequest::regrasFatura(),
                StoreFaturaApiRequest::mensagensFatura()
            );

            if ($validador->fails()) {
                $falhadas++;
                $erros["faturas.{$indice}"] = $validador->errors()->toArray();
                $resultados[] = $this->resultadoLote($indice, $referencia, 'erro', null, [], $validador->errors()->toArray());

                continue;
            }

            $data = $validador->validated();
            $referencia = $data['numero_fatura'] ?? null;

            $existente = $this->jaRegistada($data);

            if ($existente !== null) {
                $repetidas++;
                $resultados[] = $this->resultadoLote(
                    $indice,
                    $referencia,
                    'repetida',
                    $this->formatar($existente->load('items')),
                    ["fatura ja registada ({$referencia})"]
                );

                continue;
            }

            try {
                [$despesa, $avisosCriacao] = $this->registar($data);

                $registadas++;
                $resultados[] = $this->resultadoLote($indice, $referencia, 'registada', $this->formatar($despesa), $avisosCriacao);
            } catch (ValidationException $excepcao) {
                $falhadas++;
                $erros["faturas.{$indice}"] = $excepcao->errors();
                $resultados[] = $this->resultadoLote($indice, $referencia, 'erro', null, [], $excepcao->errors());
            } catch (Throwable $excepcao) {
                // Cada fatura tem a sua transaccao, por isso as anteriores
                // entraram. Devolver 500 aqui deixava quem enviou sem saber
                // quais — e a repetir o lote todo por causa de uma.
                Log::error('falha ao registar fatura do lote', [
                    'indice' => $indice,
                    'numero_fatura' => $referencia,
                    'excepcao' => $excepcao,
                ]);

                $mensagem = ['fatura' => ['Erro inesperado ao registar: '.$excepcao->getMessage()]];

                $falhadas++;
                $erros["faturas.{$indice}"] = $mensagem;
                $resultados[] = $this->resultadoLote($indice, $referencia, 'erro', null, [], $mensagem);
            }
        }

        $total = count($resultados);

        $estado = match (true) {
            $falhadas === 0 => 201,
            $registadas + $repetidas > 0 => 207,
            default => 422,
        };

        return response()->json([
            'sucesso' => $falhadas === 0,
            'dados' => [
                'total' => $total,
                'registadas' => $registadas,
                'repetidas' => $repetidas,
                'falhadas' => $falhadas,
                'faturas' => $resultados,
            ],
            'avisos' => [sprintf(
                '%d faturas: %d registadas, %d repetidas, %d falhadas.',
                $total,
                $registadas,
                $repetidas,
                $falhadas
            )],
            'erros' => $erros,
        ], $estado);
    }

    /**
     * A foto ou o PDF da fatura, para uma despesa que ja existe.
     *
     * Separado do store porque o corpo deste e o ficheiro e o do store e JSON:
     * uma foto de telemovel nao cabe numa mensagem de texto. Fica no mesmo sitio
     * e no mesmo campo que o ecra usa (`ficheiro_path`, disco public), para as
     * duas vias mostrarem a mesma imagem.
     */
    public function ficheiro(Request $request, Despesa $despesa): JsonResponse
    {
        try {
            $request->validate([
                'ficheiro' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:20480'],
            ]);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $avisos = [];

        // Substituir apaga o anterior: uma foto orfa no disco nunca mais e vista
        // por ninguem.
        if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
            $avisos[] = 'a foto anterior foi substituida.';
        }

        $despesa->update([
            'ficheiro_path' => $request->file('ficheiro')->store('despesas', 'public'),
        ]);

        return $this->ok([
            'despesa_id' => $despesa->id,
            'numero_fatura' => $despesa->numero_fatura,
            'ficheiro_path' => $despesa->ficheiro_path,
            'ficheiro_url' => Storage::disk('public')->url($despesa->ficheiro_path),
        ], $avisos);
    }

    /**
     * A despesa que esta fatura ja criou, se existir.
     *
     * Numero + fornecedor: e o que na pratica identifica uma fatura, e e o que
     * torna seguro reenviar o mesmo lote depois de um erro de rede.
     *
     * @param  array<string, mixed>  $data
     */
    private function jaRegistada(array $data): ?Despesa
    {
        if (empty($data['numero_fatura'])) {
            return null;
        }

        return Despesa::query()
            ->where('numero_fatura', $data['numero_fatura'])
            ->when(! empty($data['fornecedor']), fn ($q) => $q->where('fornecedor', $data['fornecedor']))
            ->first();
    }

    /**
     * Grava a despesa e as linhas, tudo numa transaccao.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Despesa, 1: array<int, string>}
     */
    private function registar(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $avisos = [];
            $linhas = [];
            $totalComIva = 0.0;

            foreach ($data['linhas'] as $indice => $linha) {
                $quantidade = (float) $linha['quantidade'];
                $desconto = (float) ($linha['desconto_percentagem'] ?? 0);
                $iva = (float) $linha['iva_percentagem'];

                // Nao ha coluna de desconto nesta tabela. Guardar o preco de
                // tabela era guardar um valor que nunca foi pago, por isso o
                // desconto entra no preco e fica dito nas notas — quem abrir a
                // linha no ecra vê de onde veio o numero.
                $preco = round((float) $linha['preco_unitario'] * (1 - $desconto / 100), 4);
                $notas = $linha['notas'] ?? null;

                if ($desconto > 0) {
                    $notas = trim(sprintf(
                        "%s\nPreco de tabela %s com %s%% de desconto.",
                        (string) $notas,
                        number_format((float) $linha['preco_unitario'], 4, ',', ' '),
                        rtrim(rtrim(number_format($desconto, 2, ',', ''), '0'), ',')
                    ));

                    $avisos[] = sprintf('linha %d: desconto de %s%% aplicado ao preco unitario.', $indice, rtrim(rtrim(number_format($desconto, 2, ',', ''), '0'), ','));
                }

                // Tamanho da embalagem: o nome deste projecto ganha ao do agro.
                $porQuantidade = (float) ($linha['unidades_por_quantidade'] ?? $linha['conteudo_embalagem'] ?? 1);
                $unidade = $linha['unidade_compra'] ?? $linha['unidade_embalagem'] ?? 'un';

                $totalComIva += $quantidade * $preco * (1 + $iva / 100);

                $linhas[] = [
                    'descricao' => $linha['descricao'],
                    'quantidade' => $quantidade,
                    'unidade_compra' => $unidade,
                    'unidades_por_quantidade' => $porQuantidade,
                    // Quantas unidades entraram a serio: 2 caixas de 6 sao 12.
                    'quantidade_unidades' => isset($linha['quantidade_unidades'])
                        ? (float) $linha['quantidade_unidades']
                        : $quantidade * $porQuantidade,
                    'preco_unitario' => $preco,
                    'iva_percentagem' => $iva,
                    'notas' => $notas ?: null,
                ];
            }

            $totalCalculado = round($totalComIva, 2);
            $valor = isset($data['valor']) ? round((float) $data['valor'], 2) : $totalCalculado;

            if (isset($data['valor']) && abs($valor - $totalCalculado) > 0.02) {
                $avisos[] = sprintf(
                    'o total indicado (%.2f) nao bate com a soma das linhas com IVA (%.2f); foi guardado o total indicado.',
                    $valor,
                    $totalCalculado
                );
            }

            $despesa = Despesa::create([
                'titulo' => $data['titulo'] ?? $this->tituloPorOmissao($data),
                'numero_fatura' => $data['numero_fatura'] ?? null,
                'fornecedor' => $data['fornecedor'] ?? null,
                'valor' => $valor,
                'data' => $data['data'],
                'categoria' => $data['categoria'] ?? 'entrada_produtos',
                'notas' => $data['notas'] ?? null,
            ]);

            foreach ($linhas as $linha) {
                $despesa->items()->create($linha);
            }

            return [$despesa->load('items'), $avisos];
        });
    }

    /** @param  array<string, mixed>  $data */
    private function tituloPorOmissao(array $data): string
    {
        $partes = array_filter([
            $data['fornecedor'] ?? null,
            $data['numero_fatura'] ?? null,
        ]);

        return $partes === [] ? 'Entrada de produtos' : 'Fatura '.implode(' ', $partes);
    }

    /**
     * Uma linha do resultado do lote.
     *
     * @param  array<string, mixed>|null  $dados
     * @param  array<int, string>  $avisos
     * @param  array<string, mixed>  $erros
     * @return array<string, mixed>
     */
    private function resultadoLote(
        int $indice,
        ?string $referencia,
        string $estado,
        ?array $dados = null,
        array $avisos = [],
        array $erros = []
    ): array {
        return [
            'indice' => $indice,
            'numero_fatura' => $referencia,
            'estado' => $estado,
            'sucesso' => $estado !== 'erro',
            'despesa_id' => $dados['despesa']['id'] ?? null,
            'dados' => $dados,
            'avisos' => $avisos,
            'erros' => $erros,
        ];
    }

    /** @return array<string, mixed> */
    private function formatar(Despesa $despesa): array
    {
        return [
            'despesa' => [
                'id' => $despesa->id,
                'titulo' => $despesa->titulo,
                'numero_fatura' => $despesa->numero_fatura,
                'fornecedor' => $despesa->fornecedor,
                'categoria' => $despesa->categoria,
                'valor' => $despesa->valor,
                'data' => $despesa->data?->toDateString(),
                'subtotal' => $despesa->subtotal_calculado,
                'iva' => $despesa->iva_calculado,
                'total_fatura' => $despesa->total_fatura,
                'ficheiro_url' => $despesa->ficheiro_path
                    ? Storage::disk('public')->url($despesa->ficheiro_path)
                    : null,
                'linhas' => $despesa->items->map(fn ($item) => [
                    'id' => $item->id,
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'unidade_compra' => $item->unidade_compra,
                    'unidades_por_quantidade' => $item->unidades_por_quantidade,
                    'quantidade_unidades' => $item->quantidade_unidades,
                    'preco_unitario' => $item->preco_unitario,
                    'iva_percentagem' => $item->iva_percentagem,
                    'total_sem_iva' => $item->total_sem_iva,
                    'total_com_iva' => $item->total_com_iva,
                    'custo_unitario' => $item->custo_unitario,
                ])->values()->all(),
            ],
        ];
    }
}
