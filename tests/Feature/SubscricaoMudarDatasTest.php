<?php

namespace Tests\Feature;

use App\Models\PreparacaoItem;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mudar as datas de uma subscricao (dia, periodicidade, primeira entrega,
 * adiar) tem de manter certas as entregas feitas e por realizar (Andre,
 * 01/10/2026). Hoje e quarta 01/10/2026; cliente semanal a quarta, 1a 16/09.
 */
class SubscricaoMudarDatasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 10:00:00');
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@teste.pt', 'password' => bcrypt('x'), 'role' => 'admin', 'ativo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_so_conta_como_feita_o_que_foi_preparado(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23');

        $this->assertResumo($order, feitas: 2, porRealizar: 2, proxima: '2026-10-07');
        $this->assertCalendario($order, [
            '2026-09-16' => 'entregue',
            '2026-09-23' => 'entregue',
            '2026-09-30' => 'em_atraso',
            '2026-10-07' => 'por_realizar',
        ]);
    }

    public function test_entrega_marcada_pelo_colaborador_conta_como_feita(): void
    {
        $order = $this->subscricao();
        RegistoEntrega::create(['tipo' => 'b2c', 'woo_order_id' => $order->id, 'user_id' => $this->admin->id, 'data_entrega' => '2026-09-16', 'status' => 'entregue']);

        $this->assertResumo($order, feitas: 1, porRealizar: 3, proxima: '2026-10-07');
    }

    public function test_recuar_a_primeira_entrega_nao_inventa_entregas_feitas(): void
    {
        $order = $this->subscricao(['first_delivery_at' => '2026-10-07']);
        $this->assertResumo($order, feitas: 0, porRealizar: 4, proxima: '2026-10-07');

        $this->editarPerfil($order, ['first_delivery_at' => '2026-09-23']);

        $this->assertResumo($order, feitas: 0, porRealizar: 4, proxima: '2026-10-07');
        $this->assertCalendario($order, [
            '2026-09-23' => 'em_atraso',
            '2026-09-30' => 'em_atraso',
            '2026-10-07' => 'por_realizar',
            '2026-10-14' => 'por_realizar',
        ]);
    }

    public function test_mudar_o_dia_mantem_as_entregas_feitas_e_recalcula_as_que_faltam(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23', '2026-09-30');

        $this->editarPerfil($order, ['dia_entrega' => 'segunda']);

        $this->assertResumo($order, feitas: 3, porRealizar: 1, proxima: '2026-10-05');
        $this->assertCalendario($order, [
            '2026-09-16' => 'entregue',
            '2026-09-23' => 'entregue',
            '2026-09-30' => 'entregue',
            '2026-10-05' => 'por_realizar',
        ]);
    }

    public function test_corrigir_a_primeira_entrega_para_depois_de_uma_feita_nao_a_perde(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23');

        $this->editarPerfil($order, ['first_delivery_at' => '2026-09-23']);

        $this->assertResumo($order, feitas: 2, porRealizar: 2, proxima: '2026-10-07');
        $this->assertSame(['2026-09-16', '2026-09-23', '2026-09-30', '2026-10-07'], $this->datas($order));
    }

    public function test_passar_a_quinzenal_mantem_a_feita_e_espaca_as_restantes(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-30');

        $this->editarPerfil($order, ['ciclo_entrega' => 'quinzenal']);

        $this->assertResumo($order, feitas: 2, porRealizar: 2, proxima: '2026-10-14');
        $this->assertSame(['2026-09-16', '2026-09-30', '2026-10-14', '2026-10-28'], $this->datas($order));
    }

    public function test_adiar_a_proxima_entrega_mantem_as_contagens(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23', '2026-09-30');

        $this->actingAs($this->admin)
            ->put(route('encomendas.postpone', $order), ['delivery_date' => '2026-10-07', 'postponed_until' => '2026-10-14'])
            ->assertSessionHasNoErrors();

        $this->assertResumo($order, feitas: 3, porRealizar: 1, proxima: '2026-10-14');
    }

    public function test_adiar_uma_entrega_em_atraso_para_a_frente(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23');

        $this->actingAs($this->admin)
            ->put(route('encomendas.postpone', $order), ['delivery_date' => '2026-09-30', 'postponed_until' => '2026-10-07'])
            ->assertSessionHasNoErrors();

        $this->assertResumo($order, feitas: 2, porRealizar: 2, proxima: '2026-10-07');
        $this->assertSame(['2026-09-16', '2026-09-23', '2026-10-07', '2026-10-14'], $this->datas($order));
    }

    public function test_adiar_uma_entrega_para_o_proprio_dia_salta_essa_entrega(): void
    {
        // Escolher a entrega de 07/10 e pedir para a adiar "para 07/10" deixava-a
        // "Adiada" no mesmo dia e o ciclo nao ganhava mais uma entrega no fim.
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23', '2026-09-30');

        $this->actingAs($this->admin)
            ->put(route('encomendas.postpone', $order), ['delivery_date' => '2026-10-07', 'postponed_until' => '2026-10-07'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-09-16', '2026-09-23', '2026-09-30', '2026-10-14'], $this->datas($order));
        $this->assertResumo($order, feitas: 3, porRealizar: 1, proxima: '2026-10-14');
    }

    public function test_adiar_a_proxima_entrega_para_o_proprio_dia_empurra_as_restantes(): void
    {
        $order = $this->subscricao(['first_delivery_at' => '2026-09-23']);
        $this->preparada($order, '2026-09-23', '2026-09-30');

        $this->actingAs($this->admin)
            ->put(route('encomendas.postpone', $order), ['delivery_date' => '', 'postponed_until' => '2026-10-07'])
            ->assertSessionHasNoErrors();

        $this->assertCalendario($order, [
            '2026-09-23' => 'entregue',
            '2026-09-30' => 'entregue',
            '2026-10-14' => 'adiada',
            '2026-10-21' => 'por_realizar',
        ]);
        $this->assertResumo($order, feitas: 2, porRealizar: 2, proxima: '2026-10-14');
    }

    public function test_a_entrega_adiada_pode_voltar_a_ser_escolhida_para_adiar(): void
    {
        $order = $this->subscricao(['first_delivery_at' => '2026-09-23', 'postponed_until' => '2026-10-07']);
        $this->preparada($order, '2026-09-23', '2026-09-30');

        $this->actingAs($this->admin)->get(route('encomendas.show', $order))
            ->assertOk()
            ->assertSee('<option value="2026-10-07">07/10/2026 (adiada)</option>', false)
            ->assertSee('name="postponed_until" type="date" value=""', false);
    }

    public function test_marcar_entrega_em_atraso_como_feita(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23');

        $this->actingAs($this->admin)
            ->put(route('encomendas.entrega-feita', $order), ['data' => '2026-09-30'])
            ->assertSessionHasNoErrors();

        $this->assertResumo($order, feitas: 3, porRealizar: 1, proxima: '2026-10-07');

        // Repetir nao duplica nada.
        $this->actingAs($this->admin)->put(route('encomendas.entrega-feita', $order), ['data' => '2026-09-30']);
        $this->assertSame(1, PreparacaoItem::where('woo_order_id', $order->id)->whereDate('data_preparacao', '2026-09-30')->count());
    }

    public function test_nao_marca_como_feita_uma_data_que_nao_e_da_encomenda_nem_futura(): void
    {
        $order = $this->subscricao();

        $this->actingAs($this->admin)
            ->put(route('encomendas.entrega-feita', $order), ['data' => '2026-09-29'])
            ->assertSessionHasErrors('data');
        $this->actingAs($this->admin)
            ->put(route('encomendas.entrega-feita', $order), ['data' => '2026-10-07'])
            ->assertSessionHasErrors('data');

        $this->assertSame(0, PreparacaoItem::count());
    }

    public function test_depois_de_mudar_o_dia_nao_aparece_numa_quinta_entrega_na_preparacao(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23', '2026-09-30');

        $this->editarPerfil($order, ['dia_entrega' => 'segunda']);
        $order = $order->fresh();

        $this->assertTrue($order->temEntregaB2cNaData('2026-10-05'));
        $this->assertFalse($order->temEntregaB2cNaData('2026-10-12'));
        $this->assertSame('2026-10-05', $order->fimCicloSubscricao()->toDateString());
    }

    public function test_ficha_da_encomenda_mostra_o_botao_nas_entregas_em_atraso(): void
    {
        $order = $this->subscricao();
        $this->preparada($order, '2026-09-16', '2026-09-23');

        $this->actingAs($this->admin)
            ->get(route('encomendas.show', $order))
            ->assertOk()
            ->assertSee('Foi entregue')
            ->assertSee('30/09/2026 (em atraso)');
    }

    private function subscricao(array $atributos = []): WooOrder
    {
        static $wooId = 9000;

        return WooOrder::create($atributos + [
            'woo_id' => ++$wooId,
            'source_type' => 'subscription',
            'status' => 'subscricao',
            'billing_name' => 'Cliente Teste',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
            'first_delivery_at' => '2026-09-16',
            'total' => 40,
        ]);
    }

    private function preparada(WooOrder $order, string ...$datas): void
    {
        foreach ($datas as $data) {
            PreparacaoItem::create(['data_preparacao' => $data, 'tipo' => 'b2c', 'woo_order_id' => $order->id, 'feito' => true]);
        }
    }

    private function editarPerfil(WooOrder $order, array $mudancas): void
    {
        $order = $order->fresh();

        $this->actingAs($this->admin)
            ->from(route('encomendas.show', $order))
            ->put(route('encomendas.profile.update', $order), array_merge([
                'source_type' => $order->source_type,
                'billing_name' => $order->billing_name,
                'dia_entrega' => $order->dia_entrega,
                'ciclo_entrega' => $order->ciclo_entrega,
                'first_delivery_at' => $order->first_delivery_at?->toDateString(),
            ], $mudancas))
            ->assertSessionHasNoErrors();
    }

    private function assertResumo(WooOrder $order, int $feitas, int $porRealizar, ?string $proxima): void
    {
        $this->assertSame(
            ['total' => 4, 'feitas' => $feitas, 'por_realizar' => $porRealizar, 'proxima' => $proxima],
            $order->fresh()->entregasSubscricao()
        );
    }

    private function assertCalendario(WooOrder $order, array $esperado): void
    {
        $this->assertSame(
            $esperado,
            $order->fresh()->calendarioEntregas()->mapWithKeys(fn (array $e): array => [$e['data_key'] => $e['status']])->all()
        );
    }

    private function datas(WooOrder $order): array
    {
        return $order->fresh()->calendarioEntregas()->pluck('data_key')->all();
    }
}
