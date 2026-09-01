<?php

namespace Tests\Unit;

use App\Models\WooOrder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscricaoCicloTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function subscricao(array $atributos = []): WooOrder
    {
        $order = new WooOrder(array_merge([
            'source_type' => 'subscription',
            'first_delivery_at' => '2026-08-12',
            'delivery_dates' => [],
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'quinzenal',
        ], $atributos));

        $order->setRelation('preparacaoItems', collect());
        $order->setRelation('registoEntregas', collect());

        return $order;
    }

    private function datas(WooOrder $order): array
    {
        $metodo = new \ReflectionMethod($order, 'datasSubscricao');
        $metodo->setAccessible(true);

        return $metodo->invoke($order)->all();
    }

    public function test_subscricao_quinzenal_tem_sempre_quatro_entregas(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $this->assertSame(
            ['2026-08-12', '2026-08-26', '2026-09-09', '2026-09-23'],
            $this->datas($this->subscricao())
        );
    }

    public function test_subscricao_semanal_tem_sempre_quatro_entregas(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $this->assertSame(
            ['2026-08-12', '2026-08-19', '2026-08-26', '2026-09-02'],
            $this->datas($this->subscricao(['ciclo_entrega' => 'semanal']))
        );
    }

    public function test_a_primeira_entrega_cai_sempre_no_dia_de_entrega_do_cliente(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        // 10/08 e uma segunda; o cliente e de quarta, por isso comeca a 12/08.
        $datas = $this->datas($this->subscricao(['first_delivery_at' => '2026-08-10']));

        $this->assertSame('2026-08-12', $datas[0]);
    }

    public function test_pausa_com_fim_empurra_as_entregas_e_mantem_o_total(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $datas = $this->datas($this->subscricao([
            'pausada_em' => '2026-09-01',
            'pausada_ate' => '2026-09-30',
        ]));

        // 09/09 e 23/09 caem na pausa: nao se perdem, empurram-se para outubro.
        $this->assertSame(['2026-08-12', '2026-08-26', '2026-10-07', '2026-10-21'], $datas);
        $this->assertCount(4, $datas);
    }

    public function test_pausa_sem_fim_para_o_ciclo(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $order = $this->subscricao(['pausada_em' => '2026-09-01']);

        $this->assertSame(['2026-08-12', '2026-08-26'], $this->datas($order));
        $this->assertTrue($order->estaPausada());
        $this->assertTrue($order->pausaSemFim());
    }

    public function test_nao_ha_entrega_dentro_da_pausa_nem_com_adiamento(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $order = $this->subscricao([
            'pausada_em' => '2026-09-01',
            'pausada_ate' => '2026-09-30',
            'postponed_until' => '2026-09-16',
        ]);

        // O adiamento manda sobre o ciclo, mas a pausa manda sobre o adiamento.
        $this->assertFalse($order->temEntregaB2cNaData('2026-09-16'));
        $this->assertFalse($order->temEntregaB2cNaData('2026-09-09'));

        $semAdiamento = $this->subscricao([
            'pausada_em' => '2026-09-01',
            'pausada_ate' => '2026-09-30',
        ]);

        $this->assertFalse($semAdiamento->temEntregaB2cNaData('2026-09-09'));
        $this->assertTrue($semAdiamento->temEntregaB2cNaData('2026-10-07'));
    }

    public function test_as_datas_vindas_do_site_tambem_respeitam_a_pausa(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $order = $this->subscricao([
            'delivery_dates' => ['2026-08-12', '2026-08-26', '2026-09-09', '2026-09-23'],
            'pausada_em' => '2026-09-01',
            'pausada_ate' => '2026-09-30',
        ]);

        $this->assertSame(['2026-08-12', '2026-08-26'], $this->datas($order));
    }

    public function test_estado_on_hold_do_site_conta_como_pausa(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $order = $this->subscricao(['first_delivery_at' => '2026-09-02', 'ciclo_entrega' => 'semanal']);
        $order->status = 'on-hold';

        $this->assertTrue($order->pausadaNoSite());
        $this->assertTrue($order->estaPausada());
        $this->assertSame([], $this->datas($order));
    }

    public function test_retomar_na_app_manda_sobre_o_estado_do_site(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        $order = $this->subscricao();
        $order->status = 'on-hold';
        // E o que a app grava quando se carrega em Retomar.
        $order->pausada_ate = '2026-08-31';

        $this->assertFalse($order->estaPausada());
        $this->assertCount(4, $this->datas($order));
    }

    public function test_ciclo_terminado_pede_renovacao(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        $order = $this->subscricao();

        $this->assertSame('2026-09-23', $order->ultimaEntregaDoCiclo());
        $this->assertTrue($order->cicloTerminado());
        $this->assertTrue($order->precisaDeRenovacao());
        $this->assertTrue($order->precisaDeRenovacao(dentroDaJanela: true));
    }

    public function test_ciclo_a_meio_nao_pede_renovacao(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');

        $this->assertFalse($this->subscricao()->cicloTerminado());
        $this->assertFalse($this->subscricao()->precisaDeRenovacao());
    }

    public function test_subscricao_antiga_fica_fora_da_janela_da_renovacao_automatica(): void
    {
        Carbon::setTestNow('2026-12-01 10:00:00');

        $order = $this->subscricao();

        // Acabou ha muito: aparece na lista, mas o comando nao a renova sozinho.
        $this->assertTrue($order->precisaDeRenovacao());
        $this->assertFalse($order->precisaDeRenovacao(dentroDaJanela: true));
    }

    public function test_subscricao_em_pausa_nao_pede_renovacao(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        $order = $this->subscricao([
            'pausada_em' => '2026-09-01',
            'pausada_ate' => '2026-09-30',
        ]);

        $this->assertFalse($order->precisaDeRenovacao());
    }
}
