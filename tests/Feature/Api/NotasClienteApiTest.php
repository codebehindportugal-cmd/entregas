<?php

namespace Tests\Feature\Api;

use App\Models\NotaCliente;
use App\Models\User;
use App\Models\WooOrder;
use App\Models\WooProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Notas fixas do cliente (Andre, 07/10/2026): postas pela API, pelo telefone,
 * e vistas ao criar encomendas (backoffice e API) e na preparacao.
 */
class NotasClienteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.claude.api_token' => 'test-token']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'source_type' => 'order',
            'raw_payload' => ['shipping' => ['address_1' => 'Rua Velha 1', 'postcode' => '2500-001', 'city' => 'Caldas da Rainha']],
        ], $extra));
    }

    public function test_sem_token_nao_ha_api(): void
    {
        $this->putJson('/api/v1/clientes/notas', ['telefone' => '912345678', 'notas' => 'x'])->assertUnauthorized();
    }

    public function test_guarda_pelo_telefone_normalizado_e_aparece_no_get_clientes(): void
    {
        $this->encomendaDaJoana();

        $this->putJson('/api/v1/clientes/notas', ['telefone' => '912 345 678', 'notas' => 'Nao gosta de kiwi'], $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.telefone_normalizado', '912345678')
            ->assertJsonPath('dados.nome', 'Joana Costa')
            ->assertJsonPath('dados.notas_cliente', 'Nao gosta de kiwi');

        $this->getJson('/api/v1/clientes?telefone=00351912345678', $this->comToken())
            ->assertOk()
            ->assertJsonPath('dados.notas_cliente', 'Nao gosta de kiwi')
            ->assertJsonPath('dados.cliente.notas_cliente', 'Nao gosta de kiwi');
    }

    public function test_acrescentar_substituir_e_apagar(): void
    {
        $url = '/api/v1/clientes/notas';
        $this->putJson($url, ['telefone' => '912345678', 'notas' => 'Deixar na portaria'], $this->comToken())->assertOk();
        $this->putJson($url, ['telefone' => '912345678', 'notas' => 'Sem kiwi', 'modo' => 'acrescentar'], $this->comToken())
            ->assertJsonPath('dados.notas_cliente', "Deixar na portaria\nSem kiwi");
        $this->putJson($url, ['telefone' => '912345678', 'notas' => 'So isto'], $this->comToken())
            ->assertJsonPath('dados.notas_cliente', 'So isto');
        $this->putJson($url, ['telefone' => '912345678', 'notas' => ''], $this->comToken())
            ->assertOk()->assertJsonPath('dados.apagadas', true);

        $this->assertSame(0, NotaCliente::count());
    }

    public function test_cliente_ainda_sem_encomendas_pode_ter_notas(): void
    {
        $this->putJson('/api/v1/clientes/notas', ['telefone' => '933 111 222', 'nome' => 'Rui', 'notas' => 'Cliente novo, ligar antes'], $this->comToken())
            ->assertOk()->assertJsonPath('dados.cliente_tem_encomendas', false);

        $this->getJson('/api/v1/clientes?telefone=933111222', $this->comToken())
            ->assertJsonPath('dados.encontrado', false)
            ->assertJsonPath('dados.notas_cliente', 'Cliente novo, ligar antes');
    }

    public function test_telefone_invalido_e_notas_em_falta(): void
    {
        $this->putJson('/api/v1/clientes/notas', ['telefone' => '123'], $this->comToken())
            ->assertStatus(422)
            ->assertJsonPath('erros.0.codigo', 'TELEFONE_INVALIDO')
            ->assertJsonPath('erros.1.codigo', 'NOTAS_EM_FALTA');
    }

    public function test_validar_encomenda_avisa_as_notas_do_cliente(): void
    {
        $this->encomendaDaJoana();
        WooProduct::factory()->create(['name' => 'Maca Royal Gala', 'regular_price' => 0.60]);
        NotaCliente::create(['telefone' => '912345678', 'notas' => 'Nao gosta de kiwi']);

        $resposta = $this->postJson('/api/v1/encomendas/validar', [
            'cliente' => ['telefone' => '912345678'],
            'linhas' => [['texto' => 'maca', 'quantidade' => 3, 'unidade' => 'un']],
        ], $this->comToken())->assertOk();

        $resposta->assertJsonPath('dados.cliente.notas_cliente', 'Nao gosta de kiwi');
        $this->assertContains('CLIENTE_TEM_NOTAS', collect($resposta->json('avisos'))->pluck('codigo')->all());
    }

    public function test_notas_aparecem_no_backoffice_e_na_preparacao(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $admin = User::factory()->admin()->create();
        $order = $this->encomendaDaJoana([
            'woo_id' => 321, 'source_type' => 'subscription', 'status' => 'active',
            'billing_phone' => '912345678',
            'dia_entrega' => 'quarta', 'ciclo_entrega' => 'quinzenal', 'first_delivery_at' => '2026-08-12',
            'delivery_dates' => [], 'scheduled_delivery_at' => null, 'postponed_until' => null,
        ]);
        NotaCliente::create(['telefone' => '912345678', 'notas' => 'Nao gosta de kiwi']);

        $this->actingAs($admin)->get(route('encomendas.create', ['perfil' => $order->id]))->assertOk()->assertSee('Nao gosta de kiwi');
        $this->actingAs($admin)->get(route('encomendas.show', $order))->assertOk()->assertSee('Nao gosta de kiwi');
        $this->actingAs($admin)->get(route('preparacao.index', ['inicio' => '2026-09-08', 'fim' => '2026-09-10']))
            ->assertOk()->assertSee('Joana Costa')->assertSee('Nao gosta de kiwi');
    }
}
