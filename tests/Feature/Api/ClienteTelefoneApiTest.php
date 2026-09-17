<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WooOrder;
use App\Models\WooProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Os perfis B2C repetem-se (cada encomenda e um). O cliente identifica-se pelo
 * telefone, seja qual for o formato em que ficou gravado.
 */
class ClienteTelefoneApiTest extends TestCase
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

    private function comToken(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    private function encomendaDaJoana(array $extra = []): WooOrder
    {
        return WooOrder::factory()->create(array_merge([
            'billing_name' => 'Joana Costa',
            'billing_phone' => '+351 912 345 678',
            'billing_email' => 'joana@example.test',
            'source_type' => 'order',
            'dia_entrega' => 'sabado',
            'ordered_at' => '2026-08-01 10:00:00',
            'raw_payload' => [
                'shipping' => ['address_1' => 'Rua Velha 1', 'postcode' => '2500-001', 'city' => 'Caldas da Rainha'],
            ],
        ], $extra));
    }

    private function validar(array $cliente, array $extra = []): \Illuminate\Testing\TestResponse
    {
        WooProduct::factory()->create(['name' => 'Maca Royal Gala', 'regular_price' => 0.60]);

        return $this->postJson('/api/v1/encomendas/validar', array_merge([
            'cliente' => $cliente,
            'linhas' => [['texto' => 'maca', 'quantidade' => 3, 'unidade' => 'un']],
        ], $extra), $this->comToken());
    }

    private function codigosDosAvisos(\Illuminate\Testing\TestResponse $resposta): array
    {
        return collect($resposta->json('avisos'))->pluck('codigo')->all();
    }

    public function test_clientes_junta_as_encomendas_do_mesmo_telefone_em_formatos_diferentes(): void
    {
        $antiga = $this->encomendaDaJoana();
        $recente = $this->encomendaDaJoana([
            'billing_phone' => '912345678',
            'ordered_at' => '2026-09-10 10:00:00',
            'raw_payload' => [],
        ]);
        $this->encomendaDaJoana(['billing_phone' => '00351 912-345-678', 'ordered_at' => '2026-07-01 10:00:00']);
        WooOrder::factory()->create(['billing_name' => 'Outra Pessoa', 'billing_phone' => '962345678']);

        $this->getJson('/api/v1/clientes?telefone=912 345 678', $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.encontrado', true)
            ->assertJsonPath('dados.telefone_normalizado', '912345678')
            ->assertJsonPath('dados.cliente.total_encomendas', 3)
            ->assertJsonPath('dados.cliente.perfil_woo_order_id', $recente->id)
            ->assertJsonPath('dados.cliente.nome', 'Joana Costa')
            // A encomenda mais recente nao tem morada: vem da seguinte que a tem.
            ->assertJsonPath('dados.cliente.morada', 'Rua Velha 1')
            ->assertJsonPath('dados.cliente.codigo_postal', '2500-001')
            ->assertJsonPath('dados.cliente.encomendas.1.id', $antiga->id);
    }

    public function test_clientes_sem_resultado_e_telefone_invalido(): void
    {
        $this->getJson('/api/v1/clientes?telefone=912345678', $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.encontrado', false)
            ->assertJsonPath('dados.cliente', null);

        $this->getJson('/api/v1/clientes?telefone=123', $this->comToken())
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'TELEFONE_INVALIDO');

        $this->getJson('/api/v1/clientes?telefone=912345678')->assertUnauthorized();
    }

    public function test_validar_so_com_telefone_preenche_o_cliente(): void
    {
        $joana = $this->encomendaDaJoana();

        $resposta = $this->validar(['telefone' => '912345678'])
            ->assertOk()
            ->assertJsonPath('dados.cliente.nome', 'Joana Costa')
            ->assertJsonPath('dados.cliente.morada', 'Rua Velha 1')
            ->assertJsonPath('dados.cliente.perfil_woo_order_id', $joana->id)
            ->assertJsonPath('dados.cliente.encomendas_anteriores', 1)
            ->assertJsonPath('dados.dia_entrega', 'sabado');

        $this->assertContains('CLIENTE_EXISTENTE', $this->codigosDosAvisos($resposta));
        $this->assertNotContains('CLIENTE_NOME_DIFERENTE', $this->codigosDosAvisos($resposta));
    }

    public function test_os_dados_mandados_ganham_aos_do_perfil(): void
    {
        $this->encomendaDaJoana();

        $this->validar([
            'nome' => 'Joana',
            'telefone' => '912345678',
            'morada' => 'Rua Nova 5',
        ])
            ->assertOk()
            ->assertJsonPath('dados.cliente.nome', 'Joana')
            ->assertJsonPath('dados.cliente.morada', 'Rua Nova 5');
    }

    public function test_nome_diferente_no_mesmo_telefone_da_aviso(): void
    {
        $this->encomendaDaJoana();

        $resposta = $this->validar(['nome' => 'Rui Silva', 'telefone' => '912345678'])->assertOk();

        $this->assertContains('CLIENTE_NOME_DIFERENTE', $this->codigosDosAvisos($resposta));
    }

    public function test_telefone_com_varios_nomes_usa_o_mais_recente_e_avisa(): void
    {
        $this->encomendaDaJoana();
        $this->encomendaDaJoana(['billing_name' => 'Joana C. Marques', 'ordered_at' => '2026-09-01 10:00:00']);

        $resposta = $this->validar(['telefone' => '912345678'])
            ->assertOk()
            ->assertJsonPath('dados.cliente.nome', 'Joana C. Marques');

        $this->assertContains('CLIENTE_VARIOS_NOMES', $this->codigosDosAvisos($resposta));
    }

    public function test_telefone_desconhecido_e_cliente_novo(): void
    {
        $resposta = $this->validar([
            'nome' => 'Cliente Novo',
            'telefone' => '934567890',
        ])->assertOk()
            ->assertJsonPath('dados.cliente.perfil_woo_order_id', null)
            ->assertJsonPath('dados.cliente.encomendas_anteriores', 0);

        $this->assertContains('CLIENTE_NOVO', $this->codigosDosAvisos($resposta));
    }

    public function test_telefone_desconhecido_sem_nome_bloqueia(): void
    {
        $this->validar(['telefone' => '934567890'])
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'CLIENTE_INCOMPLETO');
    }

    public function test_perfil_escolhido_com_outro_telefone_avisa(): void
    {
        $joana = $this->encomendaDaJoana();

        $resposta = $this->validar(
            ['telefone' => '934567890'],
            ['perfil_woo_order_id' => $joana->id],
        )->assertOk()
            ->assertJsonPath('dados.cliente.nome', 'Joana Costa')
            ->assertJsonPath('dados.cliente.telefone', '934567890');

        $this->assertContains('PERFIL_TELEFONE_DIFERENTE', $this->codigosDosAvisos($resposta));
    }

    public function test_formulario_de_nova_encomenda_mostra_um_perfil_por_telefone(): void
    {
        Http::fake(['example.test/*' => Http::response([], 200)]);
        $admin = User::factory()->admin()->create();

        $this->encomendaDaJoana();
        $recente = $this->encomendaDaJoana(['billing_phone' => '912345678', 'ordered_at' => '2026-09-10 10:00:00']);
        $outro = WooOrder::factory()->create(['billing_name' => 'Outra Pessoa', 'billing_phone' => '962345678']);
        $semTelefoneA = WooOrder::factory()->create(['billing_name' => 'Sem Tel', 'billing_phone' => null, 'billing_email' => 'sem@example.test', 'ordered_at' => '2026-09-01 10:00:00']);
        WooOrder::factory()->create(['billing_name' => 'Sem Tel', 'billing_phone' => null, 'billing_email' => 'SEM@example.test', 'ordered_at' => '2026-01-01 10:00:00']);

        $this->withoutVite();

        $perfis = $this->actingAs($admin)
            ->get(route('encomendas.create'))
            ->assertOk()
            ->viewData('perfis');

        $this->assertEqualsCanonicalizing(
            [$recente->id, $outro->id, $semTelefoneA->id],
            $perfis->pluck('id')->all(),
        );
    }
}
