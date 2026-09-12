<?php

namespace App\Services;

use App\Models\WooOrder;
use App\Models\WooProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * O trabalho por tras dos dois endpoints de encomendas do chat.
 *
 * Sao dois passos de proposito. O primeiro (`validar`) le o texto ja partido em
 * linhas, resolve os produtos, converte as quantidades e nao grava nada; o
 * segundo (`prepararCriacao`) so aceita o que saiu do primeiro, e volta a
 * validar tudo do zero porque entre um e outro o preco ou o stock podem ter
 * mudado. O token existe para nao haver maneira de criar uma encomenda que
 * ninguem chegou a ver validada.
 */
class EncomendaChatService
{
    public const MINUTOS_VALIDADE = 30;

    public function __construct(
        private readonly ResolvedorProdutos $resolvedor,
        private readonly ValidadorQuantidadesEncomenda $validador,
        private readonly WooCommerceService $woocommerce,
    ) {}

    /**
     * @return array{dados: array<string, mixed>, avisos: array<int, mixed>, erros: array<int, mixed>}
     */
    public function validar(array $pedido, bool $gerarToken = true): array
    {
        $avisos = [];
        $erros = [];

        $cliente = $this->resolverCliente($pedido);

        if (blank($cliente['nome']) || blank($cliente['telefone'])) {
            $erros[] = [
                'codigo' => 'CLIENTE_INCOMPLETO',
                'linha' => null,
                'mensagem' => 'Falta o nome ou o telefone do cliente. Podes mandar tambem perfil_woo_order_id de uma encomenda antiga dele.',
                'sugestoes' => [],
            ];
        }

        if (blank($cliente['morada'])) {
            $avisos[] = [
                'codigo' => 'MORADA_EM_FALTA',
                'linha' => null,
                'mensagem' => 'Encomenda sem morada de entrega. Da para criar, mas confirma a morada com o cliente.',
            ];
        }

        $linhas = [];
        $total = 0.0;

        foreach (array_values($pedido['linhas'] ?? []) as $indice => $linha) {
            $numero = $indice + 1;
            $resultado = $this->validarLinha($linha, $numero);

            $linhas[] = $resultado['linha'];
            $avisos = array_merge($avisos, $resultado['avisos']);
            $erros = array_merge($erros, $resultado['erros']);
            $total += (float) ($resultado['linha']['subtotal'] ?? 0);
        }

        if ($linhas === []) {
            $erros[] = [
                'codigo' => 'SEM_LINHAS',
                'linha' => null,
                'mensagem' => 'A encomenda nao tem nenhuma linha.',
                'sugestoes' => [],
            ];
        }

        $cupoes = $this->validarCupoes($pedido['cupoes'] ?? [], $avisos, $erros);

        $dados = [
            'cliente' => $cliente,
            'linhas' => $linhas,
            'total_estimado' => round($total, 2),
            'dia_entrega' => $pedido['dia_entrega'] ?? ($cliente['dia_entrega'] ?? null),
            'data_entrega' => $pedido['data_entrega'] ?? null,
            'notas' => $pedido['notas'] ?? null,
            'cupoes' => $cupoes,
        ];

        if ($erros === [] && $gerarToken) {
            $dados['token_confirmacao'] = $this->guardarToken($pedido, $dados);
            $dados['valido_ate'] = now()->addMinutes(self::MINUTOS_VALIDADE)->toIso8601String();
        }

        return ['dados' => $dados, 'avisos' => $avisos, 'erros' => $erros];
    }

    /**
     * Segunda validacao, a partir do token. Devolve o pedido original revalidado.
     *
     * @return array{pedido: array<string, mixed>|null, dados: array<string, mixed>, avisos: array<int, mixed>, erros: array<int, mixed>}
     */
    public function prepararCriacao(string $token): array
    {
        $guardado = Cache::get($this->chaveToken($token));

        if (! is_array($guardado)) {
            return [
                'pedido' => null,
                'dados' => [],
                'avisos' => [],
                'erros' => [[
                    'codigo' => 'TOKEN_INVALIDO',
                    'linha' => null,
                    'mensagem' => 'Validacao expirada ou desconhecida. Volta a correr /validar — a validacao dura '.self::MINUTOS_VALIDADE.' minutos.',
                    'sugestoes' => [],
                ]],
            ];
        }

        // De propósito do zero: entre validar e confirmar o preco ou o stock podem ter mudado.
        $revalidado = $this->validar($guardado['pedido'], gerarToken: false);

        return [
            'pedido' => $guardado['pedido'],
            'dados' => $revalidado['dados'],
            'avisos' => $revalidado['avisos'],
            'erros' => $revalidado['erros'],
        ];
    }

    public function esquecerToken(string $token): void
    {
        Cache::forget($this->chaveToken($token));
    }

    /**
     * Cria mesmo a encomenda no site e guarda-a ca.
     *
     * @return array{order: WooOrder, payment_url: string|null}
     */
    public function criar(array $dados, string $referenciaExterna): array
    {
        $cliente = $dados['cliente'];

        $resultado = $this->woocommerce->createPendingOrder([
            'billing_name' => $cliente['nome'],
            'billing_phone' => $cliente['telefone'],
            'billing_email' => $cliente['email'],
            'billing_address_1' => $cliente['morada'],
            'billing_city' => $cliente['cidade'],
            'billing_postcode' => $cliente['codigo_postal'],
            'shipping_address_1' => $cliente['morada'],
            'shipping_city' => $cliente['cidade'],
            'shipping_postcode' => $cliente['codigo_postal'],
            'customer_language' => $cliente['idioma'],
            'dia_entrega' => $dados['dia_entrega'],
            'scheduled_delivery_at' => $dados['data_entrega'],
            'customer_notes' => $dados['notas'],
            'coupon_codes' => $dados['cupoes'],
            'products' => collect($dados['linhas'])
                ->filter(fn (array $linha): bool => filled($linha['produto']['id'] ?? null) && filled($linha['quantidade_woo'] ?? null))
                ->map(fn (array $linha): array => [
                    'woo_product_id' => (int) $linha['produto']['id'],
                    'quantity' => (int) $linha['quantidade_woo'],
                ])
                ->values()
                ->all(),
        ]);

        $resultado['order']->forceFill(['referencia_externa' => $referenciaExterna])->save();

        return $resultado;
    }

    /** @return array{linha: array<string, mixed>, avisos: array<int, mixed>, erros: array<int, mixed>} */
    private function validarLinha(array $linha, int $numero): array
    {
        $texto = (string) ($linha['texto'] ?? '');
        $quantidade = (float) ($linha['quantidade'] ?? 0);
        $unidade = $linha['unidade'] ?? null;

        $base = [
            'linha' => $numero,
            'texto_original' => $texto,
            'quantidade_pedida' => $quantidade,
            'unidade_pedida' => $unidade,
            'produto' => null,
            'quantidade_woo' => null,
            'equivalencia' => null,
            'preco_unitario' => null,
            'subtotal' => null,
            'candidatos' => [],
        ];

        $produto = filled($linha['woo_product_id'] ?? null)
            ? $this->resolvedor->porId((int) $linha['woo_product_id'])
            : null;

        if ($produto === null && filled($linha['woo_product_id'] ?? null)) {
            return [
                'linha' => $base,
                'avisos' => [],
                'erros' => [$this->comLinha($numero, 'PRODUTO_DESCONHECIDO', "Nao existe nenhum produto com o id {$linha['woo_product_id']}.")],
            ];
        }

        $avisos = [];

        if ($produto === null) {
            $resolucao = $this->resolvedor->resolver($texto);
            $produto = $resolucao['produto'];
            $base['candidatos'] = $this->formatarCandidatos($resolucao['candidatos']);

            if ($produto === null) {
                $codigo = $resolucao['confianca'] === 'ambigua' ? 'PRODUTO_AMBIGUO' : 'PRODUTO_NAO_ENCONTRADO';
                $mensagem = $resolucao['confianca'] === 'ambigua'
                    ? "\"{$texto}\" pode ser mais do que um produto — pergunta ao cliente qual."
                    : "\"{$texto}\" nao corresponde a nenhum produto disponivel no site.";

                return [
                    'linha' => $base,
                    'avisos' => [],
                    'erros' => [$this->comLinha($numero, $codigo, $mensagem, $base['candidatos'])],
                ];
            }

            if ($resolucao['confianca'] === 'provavel') {
                $avisos[] = $this->comLinha($numero, 'PRODUTO_APROXIMADO', "\"{$texto}\" lido como {$produto->name}.");
            }
        }

        if (! $produto->compraAtiva()) {
            return [
                'linha' => $base,
                'avisos' => $avisos,
                'erros' => [$this->comLinha($numero, 'PRODUTO_INDISPONIVEL', "{$produto->name} nao esta disponivel para compra de momento.")],
            ];
        }

        $base['produto'] = $this->formatarProduto($produto);

        $validacao = $this->validador->validarLinha($produto, $quantidade, $unidade);
        $avisos = array_merge($avisos, array_map(fn (array $a): array => $a + ['linha' => $numero], $validacao['avisos']));
        $erros = array_map(fn (array $e): array => $e + ['linha' => $numero], $validacao['erros']);

        if ($validacao['quantidade_woo'] !== null) {
            $preco = $produto->precoVenda();
            $base['quantidade_woo'] = $validacao['quantidade_woo'];
            $base['equivalencia'] = $validacao['equivalencia'];
            $base['preco_unitario'] = $preco;
            $base['subtotal'] = $preco !== null ? round($preco * $validacao['quantidade_woo'], 2) : null;

            if ($preco === null) {
                $avisos[] = $this->comLinha($numero, 'SEM_PRECO', "{$produto->name} nao tem preco no site; o total fica por baixo do real.");
            }
        }

        return ['linha' => $base, 'avisos' => $avisos, 'erros' => $erros];
    }

    private function resolverCliente(array $pedido): array
    {
        $cliente = (array) ($pedido['cliente'] ?? []);
        $perfil = filled($pedido['perfil_woo_order_id'] ?? null)
            ? WooOrder::find((int) $pedido['perfil_woo_order_id'])
            : null;

        $billing = (array) ($perfil?->raw_payload['billing'] ?? []);
        $shipping = (array) ($perfil?->raw_payload['shipping'] ?? []);

        return [
            'nome' => $this->primeiro($cliente['nome'] ?? null, $perfil?->billing_name),
            'telefone' => $this->primeiro($cliente['telefone'] ?? null, $perfil?->billing_phone),
            'email' => $this->primeiro($cliente['email'] ?? null, $perfil?->billing_email),
            'morada' => $this->primeiro($cliente['morada'] ?? null, $shipping['address_1'] ?? null, $billing['address_1'] ?? null),
            'codigo_postal' => $this->primeiro($cliente['codigo_postal'] ?? null, $shipping['postcode'] ?? null, $billing['postcode'] ?? null),
            'cidade' => $this->primeiro($cliente['cidade'] ?? null, $shipping['city'] ?? null, $billing['city'] ?? null),
            'idioma' => $this->primeiro($cliente['idioma'] ?? null, $perfil?->customer_language) ?? 'pt',
            'dia_entrega' => $perfil?->dia_entrega,
            'perfil_woo_order_id' => $perfil?->id,
        ];
    }

    private function validarCupoes(array $cupoes, array &$avisos, array &$erros): array
    {
        $codigos = collect($cupoes)->filter()->unique()->values()->all();

        if ($codigos === []) {
            return [];
        }

        try {
            $registados = collect($this->woocommerce->fetchCoupons())->pluck('code')->map(fn ($c) => mb_strtolower((string) $c))->all();
        } catch (Throwable $exception) {
            $avisos[] = [
                'codigo' => 'CUPOES_POR_VALIDAR',
                'linha' => null,
                'mensagem' => 'Nao deu para validar os cupoes no site: '.$exception->getMessage(),
            ];

            return $codigos;
        }

        $invalidos = collect($codigos)
            ->reject(fn (string $codigo): bool => in_array(mb_strtolower($codigo), $registados, true))
            ->values()
            ->all();

        if ($invalidos !== []) {
            $erros[] = [
                'codigo' => 'CUPAO_INVALIDO',
                'linha' => null,
                'mensagem' => 'Cupoes que nao existem no site: '.implode(', ', $invalidos),
                'sugestoes' => [],
            ];
        }

        return $codigos;
    }

    private function guardarToken(array $pedido, array $dados): string
    {
        $token = Str::random(40);

        Cache::put($this->chaveToken($token), [
            'pedido' => $pedido,
            'resumo' => Arr::only($dados, ['total_estimado', 'dia_entrega', 'data_entrega']),
            'criado_em' => now()->toIso8601String(),
        ], now()->addMinutes(self::MINUTOS_VALIDADE));

        return $token;
    }

    private function chaveToken(string $token): string
    {
        return 'claude:encomenda:'.$token;
    }

    private function formatarProduto(WooProduct $produto): array
    {
        return [
            'id' => $produto->id,
            'woo_id' => $produto->woo_id,
            'nome' => $produto->name,
            'sku' => $produto->sku,
            'unidade_venda' => $produto->unidade_venda,
            'formato' => $produto->descricaoFormato(),
        ];
    }

    private function formatarCandidatos(iterable $candidatos): array
    {
        return collect($candidatos)
            ->map(fn (array $candidato): array => $this->formatarProduto($candidato['produto']) + [
                'score' => $candidato['score'],
            ])
            ->values()
            ->all();
    }

    private function comLinha(int $numero, string $codigo, string $mensagem, array $sugestoes = []): array
    {
        return [
            'codigo' => $codigo,
            'linha' => $numero,
            'mensagem' => $mensagem,
            'sugestoes' => $sugestoes,
        ];
    }

    private function primeiro(mixed ...$valores): ?string
    {
        foreach ($valores as $valor) {
            if (filled($valor)) {
                return (string) $valor;
            }
        }

        return null;
    }
}
