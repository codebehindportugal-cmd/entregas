<?php

namespace Tests\Feature;

use App\Models\PedidoRecebido;
use App\Models\User;
use App\Models\WooProduct;
use App\Support\PedidosSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caixa de pedidos: o que chega por email/WhatsApp fica guardado e so vai para o
 * WooCommerce quando alguem carrega em Confirmar. Repetir o envio nao duplica.
 */
class PedidosRecebidosTest extends TestCase
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
            'ntfy.enabled' => false,
        ]);
    }

    private function comToken(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    private function ameixa(): WooProduct
    {
        return WooProduct::factory()->aoPeso(0.5)->create(['name' => 'Ameixa 500g', 'regular_price' => 2.00]);
    }

    private function email(array $extra = []): array
    {
        return array_merge([
            'canal' => 'email',
            'origem_id' => 'gmail-abc-1',
            'remetente' => 'Joana Costa <joana@example.test>',
            'assunto' => 'Encomenda para quarta',
            'texto_original' => "Bom dia, queria 2 kg de ameixas para quarta.\nJoana 912345678",
            'pedido' => [
                'cliente' => ['nome' => 'Joana Costa', 'telefone' => '912345678', 'morada' => 'Rua das Flores 10', 'codigo_postal' => '2500-100', 'cidade' => 'Caldas da Rainha'],
                'dia_entrega' => 'quarta',
                'linhas' => [['texto' => 'ameixas', 'quantidade' => 2, 'unidade' => 'kg']],
            ],
        ], $extra);
    }

    public function test_sem_token_nao_ha_api(): void
    {
        $this->postJson('/api/v1/pedidos-recebidos', $this->email())->assertUnauthorized();
    }

    public function test_email_interpretado_fica_pronto_sem_criar_encomenda(): void
    {
        $this->ameixa();
        Http::fake();

        $this->postJson('/api/v1/pedidos-recebidos', $this->email(), $this->comToken())
            ->assertCreated()
            ->assertJsonPath('dados.estado', 'pronto')
            ->assertJsonPath('dados.resumo.linhas.0.quantidade_woo', 4);

        Http::assertNothingSent();
        $this->assertDatabaseCount('woo_orders', 0);
    }

    public function test_o_mesmo_email_nao_entra_duas_vezes(): void
    {
        $this->ameixa();

        $this->postJson('/api/v1/pedidos-recebidos/lote', ['pedidos' => [$this->email(), $this->email()]], $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.novos', 1)
            ->assertJsonPath('dados.resultados.1.repetido', true);

        $this->assertDatabaseCount('pedidos_recebidos', 1);
    }

    public function test_sem_telefone_fica_em_falta_telefone(): void
    {
        $dados = $this->email();
        unset($dados['pedido']['cliente']['telefone']);

        $this->postJson('/api/v1/pedidos-recebidos', $dados, $this->comToken())
            ->assertCreated()
            ->assertJsonPath('dados.estado', 'falta_telefone');
    }

    public function test_quantidade_ambigua_fica_com_duvidas(): void
    {
        $this->ameixa();
        $dados = $this->email();
        $dados['pedido']['linhas'] = [['texto' => 'ameixas', 'quantidade' => 2, 'unidade' => null]];

        $this->postJson('/api/v1/pedidos-recebidos', $dados, $this->comToken())
            ->assertCreated()
            ->assertJsonPath('dados.estado', 'com_duvidas');
    }

    public function test_duvidas_do_claude_nao_deixam_ficar_pronto(): void
    {
        $this->ameixa();

        $this->postJson('/api/v1/pedidos-recebidos', $this->email(['duvidas' => ['Pediu "as do costume" — confirmar quais.']]), $this->comToken())
            ->assertCreated()
            ->assertJsonPath('dados.estado', 'com_duvidas');
    }

    public function test_confirmar_no_backoffice_cria_a_encomenda_uma_so_vez(): void
    {
        $this->ameixa();
        Http::fake(['example.test/*' => Http::response([
            'id' => 987, 'status' => 'pending', 'total' => '8.00', 'order_key' => 'wc_order_abc',
            'date_created' => '2026-10-03T10:00:00',
            'billing' => ['first_name' => 'Joana', 'last_name' => 'Costa', 'phone' => '912345678'],
            'line_items' => [['name' => 'Ameixa 500g', 'quantity' => 4, 'product_id' => 0]],
        ], 201)]);

        $id = $this->postJson('/api/v1/pedidos-recebidos', $this->email(), $this->comToken())->json('dados.id');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('pedidos-recebidos.confirmar', $id))->assertRedirect();
        $this->actingAs($admin)->post(route('pedidos-recebidos.confirmar', $id))->assertRedirect();

        $pedido = PedidoRecebido::find($id);
        $this->assertSame('criado', $pedido->estado);
        $this->assertNotNull($pedido->woo_order_id);
        $this->assertDatabaseCount('woo_orders', 1);
        $this->assertDatabaseHas('woo_orders', ['referencia_externa' => 'pr-'.$id]);
    }

    public function test_pedido_com_erros_nao_se_confirma(): void
    {
        $this->ameixa();
        Http::fake();
        $dados = $this->email();
        $dados['pedido']['linhas'] = [['texto' => 'ameixas', 'quantidade' => 2, 'unidade' => null]];
        $id = $this->postJson('/api/v1/pedidos-recebidos', $dados, $this->comToken())->json('dados.id');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('pedidos-recebidos.confirmar', $id))
            ->assertSessionHasErrors('pedido');

        Http::assertNothingSent();
    }

    public function test_corrigir_o_telefone_no_backoffice_volta_a_validar(): void
    {
        $this->ameixa();
        $dados = $this->email();
        unset($dados['pedido']['cliente']['telefone']);
        $id = $this->postJson('/api/v1/pedidos-recebidos', $dados, $this->comToken())->json('dados.id');

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('pedidos-recebidos.update', $id), ['telefone' => '912345678'])
            ->assertRedirect();

        $this->assertSame('pronto', PedidoRecebido::find($id)->estado);
    }

    public function test_paginas_do_backoffice_abrem(): void
    {
        $this->withoutVite();
        $this->ameixa();
        $id = $this->postJson('/api/v1/pedidos-recebidos', $this->email(), $this->comToken())->json('dados.id');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('pedidos-recebidos.index'))->assertOk()->assertSee('Joana Costa');
        $this->actingAs($admin)->get(route('pedidos-recebidos.show', $id))->assertOk()->assertSee('Confirmar e criar encomenda');
        $this->actingAs($admin)->get(route('definicoes-pedidos.index'))->assertOk();
    }

    public function test_chaves_do_whatsapp_ficam_encriptadas_e_editaveis(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('definicoes-pedidos.update'), [
                'gmail_etiqueta_entrada' => 'Pedidos HM',
                'whatsapp_ativo' => '1',
                'whatsapp_access_token' => 'EAAG-segredo',
                'whatsapp_app_secret' => 'app-secret',
            ])->assertRedirect();

        $raw = \App\Models\Setting::where('key', PedidosSettings::SETTING_KEY)->value('value');
        $this->assertStringNotContainsString('EAAG-segredo', $raw);
        $this->assertSame('EAAG-segredo', PedidosSettings::get('whatsapp_access_token'));
        $this->assertSame('Pedidos HM', PedidosSettings::get('gmail_etiqueta_entrada'));

        // Em branco mantem o segredo que ja estava.
        $this->put(route('definicoes-pedidos.update'), ['whatsapp_ativo' => '1', 'whatsapp_access_token' => '']);
        $this->assertSame('EAAG-segredo', PedidosSettings::get('whatsapp_access_token'));

        $this->getJson('/api/v1/pedidos-recebidos/definicoes', $this->comToken())
            ->assertOk()
            // O segundo envio veio sem etiqueta: volta a etiqueta por omissao.
            ->assertJsonPath('dados.gmail_etiqueta_entrada', 'Encomendas')
            ->assertJsonMissing(['EAAG-segredo']);
    }

    public function test_webhook_do_whatsapp_junta_mensagens_seguidas_do_mesmo_cliente(): void
    {
        PedidosSettings::guardar(['whatsapp_ativo' => '1', 'whatsapp_app_secret' => 'app-secret', 'whatsapp_verify_token' => 'palavra']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=palavra&hub_challenge=123')->assertOk()->assertSee('123');
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=errada&hub_challenge=123')->assertForbidden();

        $enviar = function (string $id, string $texto) {
            $corpo = json_encode(['entry' => [['changes' => [['value' => [
                'contacts' => [['wa_id' => '351912345678', 'profile' => ['name' => 'Joana']]],
                'messages' => [['id' => $id, 'from' => '351912345678', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $texto]]],
            ]]]]]]);

            return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $corpo, 'app-secret'),
            ], $corpo);
        };

        $enviar('wamid.1', 'Olá, queria 2 kg de ameixa')->assertOk();
        $enviar('wamid.2', 'e 6 maçãs')->assertOk();
        $enviar('wamid.2', 'e 6 maçãs')->assertOk(); // a Meta a repetir

        $this->assertDatabaseCount('pedidos_recebidos', 1);
        $pedido = PedidoRecebido::first();
        $this->assertSame('novo', $pedido->estado);
        $this->assertStringContainsString('e 6 maçãs', $pedido->texto_original);
        $this->assertSame(1, substr_count($pedido->texto_original, 'e 6 maçãs'));

        // Assinatura errada e recusada.
        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=errada'], '{}')
            ->assertForbidden();
    }

    public function test_claude_devolve_a_interpretacao_de_uma_mensagem_do_whatsapp(): void
    {
        $this->ameixa();
        $pedido = PedidoRecebido::create(['canal' => 'whatsapp', 'origem_id' => 'wamid.9', 'remetente' => 'Joana +351912345678', 'texto_original' => '2 kg de ameixa', 'estado' => 'novo', 'recebido_em' => now()]);

        $this->getJson('/api/v1/pedidos-recebidos?estado=novo', $this->comToken())
            ->assertOk()->assertJsonPath('dados.total', 1);

        $this->putJson("/api/v1/pedidos-recebidos/{$pedido->id}/interpretacao", [
            'pedido' => $this->email()['pedido'],
        ], $this->comToken())->assertOk()->assertJsonPath('dados.estado', 'pronto');
    }

    public function test_token_da_caixa_so_abre_a_caixa(): void
    {
        $this->ameixa();
        $token = PedidosSettings::gerarToken();
        $cabecalho = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/pedidos-recebidos/produtos', $cabecalho)->assertOk();
        $this->getJson('/api/v1/pedidos-recebidos/clientes?telefone=912345678', $cabecalho)->assertOk();
        $this->postJson('/api/v1/pedidos-recebidos', $this->email(), $cabecalho)->assertCreated();

        // Nao cria encomendas nem faturas.
        $this->postJson('/api/v1/encomendas/validar', $this->email()['pedido'], $cabecalho)->assertUnauthorized();
        $this->postJson('/api/v1/encomendas', ['token_confirmacao' => 'x', 'referencia_externa' => 'x', 'confirmado' => true], $cabecalho)->assertUnauthorized();

        // Gerar outro invalida o anterior.
        PedidosSettings::gerarToken();
        $this->getJson('/api/v1/pedidos-recebidos', $cabecalho)->assertUnauthorized();
    }
}
