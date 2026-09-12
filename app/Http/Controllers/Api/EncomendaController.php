<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEncomendaApiRequest;
use App\Http\Requests\Api\ValidarEncomendaApiRequest;
use App\Models\WooOrder;
use App\Models\WooProduct;
use App\Services\EncomendaChatService;
use App\Services\ResolvedorProdutos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Encomendas ditadas por WhatsApp, criadas a partir do chat.
 *
 * O cliente manda a encomenda por mensagem, eu colo o texto no chat e o chat
 * fala com estes tres endpoints: le o catalogo, valida o que interpretou e so
 * depois cria. Devolve o link de pagamento do WooCommerce para eu mandar de
 * volta ao cliente.
 *
 * Mesma forma de resposta da API de faturas (sucesso/dados/avisos/erros) e a
 * mesma autenticacao (CLAUDE_API_TOKEN em Bearer, no middleware).
 */
class EncomendaController extends Controller
{
    use RespondeJson;

    /** O catalogo como o chat precisa de o ver, com os formatos de venda. */
    public function produtos(Request $request, ResolvedorProdutos $resolvedor): JsonResponse
    {
        $q = trim((string) $request->string('q'));
        $apenasDisponiveis = ! $request->has('apenas_disponiveis') || $request->boolean('apenas_disponiveis');

        $produtos = $resolvedor->catalogo($apenasDisponiveis)
            ->when(filled($q), fn ($produtos) => $produtos->filter(
                fn (WooProduct $p): bool => str_contains(mb_strtolower($p->name.' '.$p->sku), mb_strtolower($q))
            ))
            ->map(fn (WooProduct $p): array => [
                'id' => $p->id,
                'woo_id' => $p->woo_id,
                'nome' => $p->name,
                'sku' => $p->sku,
                'aliases' => is_array($p->aliases) ? $p->aliases : [],
                'preco' => $p->precoVenda(),
                'unidade_venda' => $p->unidade_venda,
                'formato_qtd' => (float) $p->formato_qtd,
                'formato_unidade' => $p->formato_unidade,
                'peso_medio_kg' => $p->peso_medio_kg !== null ? (float) $p->peso_medio_kg : null,
                'qtd_min' => (int) $p->qtd_min,
                'qtd_max' => $p->qtd_max !== null ? (int) $p->qtd_max : null,
                'descricao_formato' => $p->descricaoFormato(),
                'unidades_confirmadas' => (bool) $p->unidades_confirmadas,
                'disponivel' => $p->compraAtiva(),
            ])
            ->values()
            ->all();

        $porConfirmar = collect($produtos)->where('unidades_confirmadas', false)->count();

        return $this->ok(
            ['total' => count($produtos), 'produtos' => $produtos],
            $porConfirmar > 0
                ? [['codigo' => 'UNIDADES_POR_CONFIRMAR', 'mensagem' => "{$porConfirmar} produtos ainda tem o formato de venda por confirmar no backoffice."]]
                : [],
        );
    }

    /** Passo 1: interpreta e converte, sem gravar nada. */
    public function validar(ValidarEncomendaApiRequest $request, EncomendaChatService $service): JsonResponse
    {
        $resultado = $service->validar($request->validated());

        // Mesmo a falhar devolve-se o que foi interpretado: e isso que permite
        // perguntar ao cliente a coisa certa em vez de "nao deu".
        if ($resultado['erros'] !== []) {
            return response()->json([
                'sucesso' => false,
                'dados' => $resultado['dados'],
                'avisos' => $resultado['avisos'],
                'erros' => $resultado['erros'],
            ], 422);
        }

        return $this->ok($resultado['dados'], $resultado['avisos']);
    }

    /** Passo 2: cria mesmo, a partir do token do passo 1. */
    public function store(StoreEncomendaApiRequest $request, EncomendaChatService $service): JsonResponse
    {
        $dados = $request->validated();
        $referencia = $dados['referencia_externa'];

        $jaExiste = WooOrder::where('referencia_externa', $referencia)->first();

        if ($jaExiste !== null) {
            return $this->ok(
                $this->encomendaCriada($jaExiste, repetida: true),
                [['codigo' => 'ENCOMENDA_REPETIDA', 'mensagem' => "Ja existia uma encomenda com a referencia {$referencia}; nao foi criada outra."]],
            );
        }

        $preparacao = $service->prepararCriacao($dados['token_confirmacao']);

        if ($preparacao['erros'] !== []) {
            return response()->json([
                'sucesso' => false,
                'dados' => $preparacao['dados'],
                'avisos' => $preparacao['avisos'],
                'erros' => $preparacao['erros'],
            ], 422);
        }

        try {
            $resultado = $service->criar($preparacao['dados'], $referencia);
        } catch (Throwable $exception) {
            return response()->json([
                'sucesso' => false,
                'dados' => null,
                'avisos' => $preparacao['avisos'],
                'erros' => [[
                    'codigo' => 'WOOCOMMERCE_FALHOU',
                    'linha' => null,
                    'mensagem' => $exception->getMessage(),
                    'sugestoes' => [],
                ]],
            ], 502);
        }

        $service->esquecerToken($dados['token_confirmacao']);

        return $this->criado(
            $this->encomendaCriada($resultado['order'], repetida: false, linhas: $preparacao['dados']['linhas'] ?? []),
            $preparacao['avisos'],
        );
    }

    private function encomendaCriada(WooOrder $order, bool $repetida, array $linhas = []): array
    {
        return [
            'encomenda_id' => $order->id,
            'woo_id' => $order->woo_id,
            'referencia_externa' => $order->referencia_externa,
            'estado' => $order->status,
            'total' => $order->total !== null ? (float) $order->total : null,
            'cliente' => $order->billing_name,
            'link_pagamento' => $order->paymentUrl(),
            'link_whatsapp' => $order->whatsappPagamentoUrl(),
            'mensagem_whatsapp' => $order->mensagemPagamentoWhatsapp(),
            'url_backoffice' => route('encomendas.show', $order),
            'repetida' => $repetida,
            'linhas' => collect($linhas)->map(fn (array $linha): array => [
                'produto' => $linha['produto']['nome'] ?? null,
                'quantidade_woo' => $linha['quantidade_woo'] ?? null,
                'equivalencia' => $linha['equivalencia'] ?? null,
                'subtotal' => $linha['subtotal'] ?? null,
            ])->values()->all(),
        ];
    }
}
