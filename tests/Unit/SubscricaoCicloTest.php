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
        $this->assertTrue($order->precisaDeRenovacao(janelaDias: 0));
    }

    public function test_o_ciclo_roda_quando_as_quatro_entregas_ja_passaram(): void
    {
        // Subscricao antiga: o primeiro ciclo acabou a 23/09/2026, mas o cliente
        // continua a receber — o ciclo seguinte comeca a partir da ultima.
        Carbon::setTestNow('2026-10-14 10:00:00');

        $datas = $this->datas($this->subscricao());

        $this->assertSame(['2026-10-07', '2026-10-21', '2026-11-04', '2026-11-18'], $datas);
        $this->assertSame('2026-09-23', $this->subscricao()->fimDoCicloAnterior());
    }

    public function test_o_ciclo_congela_depois_de_renovado(): void
    {
        Carbon::setTestNow('2026-10-14 10:00:00');

        $order = $this->subscricao(['renovada_em' => '2026-09-23']);

        // Renovada: as entregas passam a ser da encomenda nova, esta fica parada
        // no ciclo em que estava.
        $this->assertSame(['2026-08-12', '2026-08-26', '2026-09-09', '2026-09-23'], $this->datas($order));
        $this->assertFalse($order->precisaDeRenovacao(janelaDias: 0));
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

        // O ciclo ja rodou: o comando nao renova nada tao atrasado.
        $this->assertTrue($order->precisaDeRenovacao(janelaDias: 0));
        $this->assertFalse($order->precisaDeRenovacao());
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

    // Caso do Andre (17/09/2026): #16906, quinzenal a quarta, 1a entrega 12/08.
    // A 26/08 nao se entrega; as seguintes sao 02/09, 16/09 e 30/09.

    public function test_pausa_de_um_dia_recomeca_no_dia_de_entrega_seguinte(): void
    {
        Carbon::setTestNow('2026-09-17 10:00:00');

        $order = $this->subscricao(['pausada_em' => '2026-08-26', 'pausada_ate' => '2026-08-26']);

        $this->assertSame(['2026-08-12', '2026-09-02', '2026-09-16', '2026-09-30'], $this->datas($order));
        $this->assertSame(
            ['total' => 4, 'feitas' => 3, 'por_realizar' => 1, 'proxima' => '2026-09-30'],
            $order->entregasSubscricao()
        );
        $this->assertSame('2026-09-30', $order->fimCicloSubscricao()->toDateString());
    }

    public function test_pausa_de_uma_semana_recomeca_no_dia_de_entrega_seguinte(): void
    {
        Carbon::setTestNow('2026-09-17 10:00:00');

        $order = $this->subscricao(['pausada_em' => '2026-08-26', 'pausada_ate' => '2026-09-01']);

        $this->assertSame(['2026-08-12', '2026-09-02', '2026-09-16', '2026-09-30'], $this->datas($order));
    }

    public function test_pausa_semanal_recomeca_no_dia_de_entrega_seguinte(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');

        $order = $this->subscricao([
            'ciclo_entrega' => 'semanal',
            'pausada_em' => '2026-08-19',
            'pausada_ate' => '2026-08-19',
        ]);

        $this->assertSame(['2026-08-12', '2026-08-26', '2026-09-02', '2026-09-09'], $this->datas($order));
    }

    public function test_pausa_sem_fim_mantem_as_quatro_entregas_no_ciclo(): void
    {
        Carbon::setTestNow('2026-09-17 10:00:00');

        $order = $this->subscricao(['pausada_em' => '2026-08-26']);

        $this->assertSame(['2026-08-12'], $this->datas($order));
        $this->assertSame(
            ['total' => 4, 'feitas' => 1, 'por_realizar' => 3, 'proxima' => null],
            $order->entregasSubscricao()
        );
        // Sem fim nao ha data de fim de ciclo, nem se pede renovacao.
        $this->assertNull($order->fimCicloSubscricao());
        $this->assertFalse($order->cicloTerminado());
        $this->assertFalse($order->precisaDeRenovacao(0));
    }

    public function test_pausa_sem_fim_que_comeca_no_futuro_nao_pede_renovacao(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');

        $order = $this->subscricao(['pausada_em' => '2026-08-25']);

        $this->assertFalse($order->estaPausada());
        $this->assertFalse($order->precisaDeRenovacao(0));
        $this->assertSame(3, $order->entregasSubscricao()['por_realizar']);
    }

    public function test_retomar_a_data_certa_depois_de_uma_pausa_sem_fim(): void
    {
        Carbon::setTestNow('2026-09-17 10:00:00');

        // Retomar a 02/09 grava pausada_ate = 01/09 (WooOrder::retomar).
        $order = $this->subscricao(['pausada_em' => '2026-08-26', 'pausada_ate' => '2026-09-01']);

        $this->assertSame(
            ['total' => 4, 'feitas' => 3, 'por_realizar' => 1, 'proxima' => '2026-09-30'],
            $order->entregasSubscricao()
        );
    }

    public function test_adiar_uma_semana_numa_quinzenal_empurra_as_seguintes(): void
    {
        Carbon::setTestNow('2026-08-20 10:00:00');

        $order = $this->subscricao();
        $order->adiarEntregaDaSubscricaoPara('2026-08-26', '2026-09-02');

        $this->assertSame(['2026-08-12', '2026-09-02', '2026-09-16', '2026-09-30'], $this->datas($order));

        Carbon::setTestNow('2026-09-17 10:00:00');

        $entregas = $order->entregasSubscricao();
        $this->assertSame(4, $entregas['total']);
        $this->assertSame(3, $entregas['feitas']);
        $this->assertSame(1, $entregas['por_realizar']);
    }
}
