<?php

namespace Tests\Feature\Api;

use App\Models\WooOrder;
use App\Models\WooProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A API que o chat usa para transformar uma mensagem de WhatsApp em encomenda.
 *
 * O que se garante aqui: nao ha caminho da mensagem ate ao site sem alguem ter
 * visto as quantidades convertidas, e repetir o pedido nao cria duas encomendas.
 */
class EncomendaChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();

        config([
            'services.claude.api_token' => 'test-token',
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);
    }

    private function comToken(array $extra = []): array
    {
        return array_merge(['Authorization' => 'Bearer test-token'], $extra);
    }

    private function ameixa(): WooProduct
    {
        return WooProduct::factory()->aoPeso(0.5)->create([
            'name' => 'Ameixa 500g',
            'regular_price' => 2.00,
        ]);
    }

    private function maca(): WooProduct
    {
        return WooProduct::factory()->create([
            'name' => 'Maca Royal Gala',
            'regular_price' => 0.60,
        ]);
    }

    private function pedidoValido(array $linhas): array
    {
        return [
            'cliente' => [
                'nome' => 'Joana Costa',
                'telefone' => '912345678',
                'morada' => 'Rua das Flores 10',
                'codigo_postal' => '2500-100',
                'cidade' => 'Caldas da Rainha',
            ],
            'dia_entrega' => 'quarta',
            'linhas' => $linhas,
        ];
    }

    private function respostaWooCriada(): array
    {
        return [
            'id' => 987,
            'status' => 'pending',
            'total' => '8.00',
            'order_key' => 'wc_order_abc',
            'date_created' => '2026-09-12T10:00:00',
            'billing' => ['first_name' => 'Joana', 'last_name' => 'Costa', 'phone' => '912345678'],
            'line_items' => [['name' => 'Ameixa 500g', 'quantity' => 4, 'product_id' => 0]],
        ];
    }

    public function test_sem_token_nao_ha_api(): void
    {
        $this->postJson('/api/v1/encomendas/validar', [])->assertUnauthorized();
    }

    public function test_catalogo_mostra_o_formato_de_venda(): void
    {
        $this->ameixa();

        $this->getJson('/api/v1/produtos', $this->comToken())
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.produtos.0.unidade_venda', 'peso')
            ->assertJsonPath('dados.produtos.0.formato_qtd', 0.5);
    }

    public function test_dois_kg_de_ameixa_dao_quatro_embalagens_e_um_token(): void
    {
        $ameixa = $this->ameixa();

        $resposta = $this->postJson(
            '/api/v1/encomendas/validar',
            $this->pedidoValido([['texto' => 'ameixas', 'quantidade' => 2, 'unidade' => 'kg']]),
            $this->comToken(),
        );

        $resposta->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.linhas.0.produto.id', $ameixa->id)
            ->assertJsonPath('dados.linhas.0.quantidade_woo', 4)
            ->assertJsonPath('dados.total_estimado', 8);

        $this->assertNotEmpty($resposta->json('dados.token_confirmacao'));
    }

    public function test_quantidade_ambigua_nao_gera_token(): void
    {
        $this->ameixa();

        $this->postJson(
            '/api/v1/encomendas/validar',
            $this->pedidoValido([['texto' => 'ameixa', 'quantidade' => 2, 'unidade' => null]]),
            $this->comToken(),
        )
            ->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('erros.0.codigo', 'UNIDADE_EM_FALTA')
            ->assertJsonMissingPath('dados.token_confirmacao');
    }

    public function test_kg_num_produto_a_unidade_sem_peso_medio_bloqueia(): void
    {
        $this->maca();

        $this->postJson(
            '/api/v1/encomendas/validar',
            $this->pedidoValido([['texto' => 'macas', 'quantidade' => 1, 'unidade' => 'kg']]),
            $this->comToken(),
        )
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'CONVERSAO_IMPOSSIVEL');
    }

    public function test_cliente_sem_telefone_bloqueia(): void
    {
        $this->ameixa();

        $pedido = $this->pedidoValido([['texto' => 'ameixa', 'quantidade' => 2, 'unidade' => 'kg']]);
        unset($pedido['cliente']['telefone']);

        $this->postJson('/api/v1/encomendas/validar', $pedido, $this->comToken())
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'CLIENTE_INCOMPLETO');
    }

    public function test_criar_encomenda_devolve_link_de_pagamento(): void
    {
        $this->ameixa();
        Http::fake(['example.test/*' => Http::response($this->respostaWooCriada(), 201)]);

        $token = $this->postJson(
            '/api/v1/encomendas/validar',
            $this->pedidoValido([['texto' => 'ameixa', 'quantidade' => 2, 'unidade' => 'kg']]),
            $this->comToken(),
        )->json('dados.token_confirmacao');

        $resposta = $this->postJson('/api/v1/encomendas', [
            'token_confirmacao' => $token,
            'referencia_externa' => 'wa-2026-09-12-joana-01',
            'confirmado' => true,
        ], $this->comToken());

        $resposta->assertCreated()
            ->assertJsonPath('dados.woo_id', 987)
            ->assertJsonPath('dados.repetida', false);

        $this->assertStringContainsString('order-pay/987', (string) $resposta->json('dados.link_pagamento'));
        $this->assertStringContainsString('wa.me', (string) $resposta->json('dados.link_whatsapp'));
        $this->assertDatabaseHas('woo_orders', ['woo_id' => 987, 'referencia_externa' => 'wa-2026-09-12-joana-01']);
    }

    public function test_a_mesma_referencia_nao_cria_duas_encomendas(): void
    {
        $this->ameixa();
        Http::fake(['example.test/*' => Http::response($this->respostaWooCriada(), 201)]);

        $pedido = $this->pedidoValido([['texto' => 'ameixa', 'quantidade' => 2, 'unidade' => 'kg']]);

        $primeiroToken = $this->postJson('/api/v1/encomendas/validar', $pedido, $this->comToken())->json('dados.token_confirmacao');
        $this->postJson('/api/v1/encomendas', [
            'token_confirmacao' => $primeiroToken,
            'referencia_externa' => 'wa-repetida',
            'confirmado' => true,
        ], $this->comToken())->assertCreated();

        $segundoToken = $this->postJson('/api/v1/encomendas/validar', $pedido, $this->comToken())->json('dados.token_confirmacao');
        $this->postJson('/api/v1/encomendas', [
            'token_confirmacao' => $segundoToken,
            'referencia_externa' => 'wa-repetida',
            'confirmado' => true,
        ], $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.repetida', true)
            ->assertJsonPath('avisos.0.codigo', 'ENCOMENDA_REPETIDA');

        $this->assertSame(1, WooOrder::where('referencia_externa', 'wa-repetida')->count());
        Http::assertSentCount(1);
    }

    public function test_token_invalido_nao_cria_nada(): void
    {
        $this->postJson('/api/v1/encomendas', [
            'token_confirmacao' => str_repeat('x', 40),
            'referencia_externa' => 'wa-sem-token',
            'confirmado' => true,
        ], $this->comToken())
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'TOKEN_INVALIDO');

        $this->assertSame(0, WooOrder::count());
    }

    public function test_falha_do_woocommerce_devolve_502_e_nao_deixa_lixo(): void
    {
        $this->ameixa();
        Http::fake(['example.test/*' => Http::response(['message' => 'erro'], 500)]);

        $token = $this->postJson(
            '/api/v1/encomendas/validar',
            $this->pedidoValido([['texto' => 'ameixa', 'quantidade' => 2, 'unidade' => 'kg']]),
            $this->comToken(),
        )->json('dados.token_confirmacao');

        $this->postJson('/api/v1/encomendas', [
            'token_confirmacao' => $token,
            'referencia_externa' => 'wa-falhou',
            'confirmado' => true,
        ], $this->comToken())
            ->assertStatus(502)
            ->assertJsonPath('erros.0.codigo', 'WOOCOMMERCE_FALHOU');

        $this->assertSame(0, WooOrder::count());
    }
}
