<?php

namespace Tests\Unit;

use App\Models\Corporate;
use App\Models\PreparacaoItem;
use App\Models\WooOrder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CorporateTest extends TestCase
{
    public function test_frutas_para_dia_uses_base_values_when_day_has_no_specific_values(): void
    {
        $corporate = new Corporate([
            'frutas' => [
                'banana' => 4,
                'maca' => 3,
                'pera' => 2,
                'laranja' => 1,
                'kiwi' => 0,
                'uvas' => 6,
                'fruta_epoca' => 5,
            ],
            'frutas_por_dia' => [],
        ]);

        $this->assertSame([
            'banana' => 4,
            'maca' => 3,
            'pera' => 2,
            'laranja' => 1,
            'kiwi' => 0,
            'uvas' => 6.0,
            'fruta_epoca' => 5,
            'frutos_secos' => 0.0,
            'mirtilos' => 0.0,
            'framboesas' => 0.0,
            'amoras' => 0.0,
            'morangos' => 0.0,
        ], $corporate->frutasParaDia('Segunda'));
    }

    public function test_frutas_para_dia_uses_day_specific_values_when_available(): void
    {
        $corporate = new Corporate([
            'frutas' => [
                'banana' => 4,
                'maca' => 3,
                'pera' => 2,
                'laranja' => 1,
                'kiwi' => 0,
                'uvas' => 6,
                'fruta_epoca' => 5,
            ],
            'frutas_por_dia' => [
                'Quarta' => [
                    'banana' => 10,
                    'maca' => 8,
                    'pera' => 0,
                    'laranja' => 6,
                    'kiwi' => 2,
                    'uvas' => 4,
                    'fruta_epoca' => 1,
                ],
            ],
        ]);

        $this->assertSame([
            'banana' => 10,
            'maca' => 8,
            'pera' => 0,
            'laranja' => 6,
            'kiwi' => 2,
            'uvas' => 4.0,
            'fruta_epoca' => 1,
            'frutos_secos' => 0.0,
            'mirtilos' => 0.0,
            'framboesas' => 0.0,
            'amoras' => 0.0,
            'morangos' => 0.0,
        ], $corporate->frutasParaDia('Quarta'));
    }

    public function test_valor_venda_uses_single_price_for_all_fruit_pieces(): void
    {
        $corporate = new Corporate([
            'preco_venda_peca' => 0.45,
            'dias_entrega' => ['Segunda'],
            'peso_total' => 12,
            'frutas_por_dia' => [
                'Segunda' => [
                    'banana' => 5,
                    'maca' => 4,
                    'pera' => 3,
                    'uvas' => 2,
                ],
            ],
        ]);

        $this->assertSame(5.40, $corporate->valorVendaParaDia('Segunda'));
        $this->assertSame(5.40, $corporate->valorVendaPorSemana());
    }

    public function test_subscricao_counts_past_synced_dates_as_done_for_historical_context(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-01', '2026-04-08', '2026-04-15', '2026-04-22'],
            'postponed_until' => '2026-05-06',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(3, $entregas['feitas']);
        $this->assertSame(1, $entregas['por_realizar']);
        $this->assertSame('2026-05-06', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_counts_only_dates_with_done_preparation_as_done(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-01', '2026-04-08', '2026-05-06'],
        ]);
        $order->setRelation('preparacaoItems', collect([
            new PreparacaoItem([
                'data_preparacao' => '2026-04-08',
                'feito' => true,
            ]),
        ]));

        $entregas = $order->entregasSubscricao();

        $this->assertSame(2, $entregas['feitas']);
        $this->assertSame(1, $entregas['por_realizar']);
        $this->assertSame('2026-05-06', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_generates_weekly_dates_from_first_delivery_when_sync_has_no_dates(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        $order = new WooOrder([
            'first_delivery_at' => '2026-04-08',
            'next_payment_at' => '2026-05-06',
            'delivery_dates' => [],
            'dia_entrega' => 'quarta',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(4, $entregas['feitas']);
        $this->assertSame(0, $entregas['por_realizar']);
        $this->assertNull($entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_generated_dates_keep_future_deliveries_open(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        $order = new WooOrder([
            'first_delivery_at' => '2026-04-22',
            'next_payment_at' => '2026-05-20',
            'delivery_dates' => [],
            'dia_entrega' => 'quarta',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(2, $entregas['feitas']);
        $this->assertSame(2, $entregas['por_realizar']);
        $this->assertSame('2026-05-06', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_generates_fortnightly_dates_from_first_delivery(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        $order = new WooOrder([
            'first_delivery_at' => '2026-04-08',
            'next_payment_at' => '2026-06-03',
            'delivery_dates' => [],
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'quinzenal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(2, $entregas['feitas']);
        $this->assertSame(2, $entregas['por_realizar']);
        $this->assertSame('2026-05-06', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_generates_monday_dates_from_first_delivery(): void
    {
        Carbon::setTestNow('2026-04-15 10:00:00');

        $order = new WooOrder([
            'first_delivery_at' => '2026-04-06',
            'next_payment_at' => '2026-05-04',
            'delivery_dates' => [],
            'dia_entrega' => 'segunda',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(2, $entregas['feitas']);
        $this->assertSame(2, $entregas['por_realizar']);
        $this->assertSame('2026-04-20', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_ignores_cancelled_delivery_dates(): void
    {
        Carbon::setTestNow('2026-05-20 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-05-07', '2026-05-14', '2026-05-21', '2026-05-28'],
            'cancelled_delivery_dates' => ['2026-05-14'],
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(1, $entregas['feitas']);
        $this->assertSame(2, $entregas['por_realizar']);
        $this->assertSame('2026-05-21', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_postpone_replaces_next_open_delivery_date(): void
    {
        Carbon::setTestNow('2026-04-09 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-08', '2026-04-15', '2026-04-22', '2026-04-29'],
            'next_payment_at' => '2026-05-06',
            'subscription_ends_at' => '2026-04-29',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect([
            new PreparacaoItem([
                'data_preparacao' => '2026-04-08',
                'feito' => true,
            ]),
        ]));

        $order->adiarProximaEntregaPara('2026-04-16');
        $order->setRelation('preparacaoItems', collect([
            new PreparacaoItem([
                'data_preparacao' => '2026-04-08',
                'feito' => true,
            ]),
        ]));

        $entregas = $order->entregasSubscricao();

        $this->assertSame(['2026-04-08', '2026-04-16', '2026-04-22', '2026-04-29'], $order->delivery_dates);
        $this->assertSame('2026-05-06', $order->next_payment_at->toDateString());
        $this->assertSame('2026-04-29', $order->subscription_ends_at->toDateString());
        $this->assertSame(1, $entregas['feitas']);
        $this->assertSame(3, $entregas['por_realizar']);
        $this->assertSame('2026-04-16', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_postpone_to_existing_delivery_date_keeps_four_deliveries_in_cycle(): void
    {
        Carbon::setTestNow('2026-05-07 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-10', '2026-04-17', '2026-04-24', '2026-05-01'],
            'first_delivery_at' => '2026-04-10',
            'next_payment_at' => '2026-05-08',
            'subscription_ends_at' => '2026-05-01',
            'dia_entrega' => 'sabado',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $order->adiarProximaEntregaPara('2026-04-24');
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(['2026-04-10', '2026-04-24', '2026-05-02', '2026-05-09'], $order->delivery_dates);
        $this->assertSame('2026-05-09', $order->subscription_ends_at->toDateString());
        $this->assertSame(4, $entregas['total']);
        $this->assertSame(3, $entregas['feitas']);
        $this->assertSame(1, $entregas['por_realizar']);
        $this->assertSame('2026-05-09', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_historical_postpone_repairs_previously_shifted_future_dates(): void
    {
        Carbon::setTestNow('2026-05-07 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-10', '2026-04-24', '2026-05-08', '2026-05-15'],
            'first_delivery_at' => '2026-04-10',
            'next_payment_at' => '2026-05-22',
            'subscription_ends_at' => '2026-05-15',
            'dia_entrega' => 'sabado',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $order->adiarProximaEntregaPara('2026-04-24');
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(['2026-04-10', '2026-04-24', '2026-05-02', '2026-05-09'], $order->delivery_dates);
        $this->assertSame('2026-05-09', $order->subscription_ends_at->toDateString());
        $this->assertSame(4, $entregas['total']);
        $this->assertSame(3, $entregas['feitas']);
        $this->assertSame(1, $entregas['por_realizar']);
        $this->assertSame('2026-05-09', $entregas['proxima']);

        Carbon::setTestNow();
    }

    public function test_subscricao_uses_delivery_dates_for_cycle_end_and_next_order_when_payment_is_missing(): void
    {
        Carbon::setTestNow('2026-04-20 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-11', '2026-04-18', '2026-04-25', '2026-05-02'],
            'subscription_ends_at' => '2026-04-30',
            'dia_entrega' => 'sabado',
            'ciclo_entrega' => 'semanal',
        ]);

        $this->assertSame('2026-05-02', $order->fimCicloSubscricao()?->toDateString());
        $this->assertSame('2026-05-09', $order->proximaEncomendaSubscricao()?->toDateString());

        Carbon::setTestNow();
    }

    public function test_subscricao_completed_cycle_has_no_next_order_badge(): void
    {
        Carbon::setTestNow('2026-05-07 10:00:00');

        $order = new WooOrder([
            'delivery_dates' => ['2026-04-10', '2026-04-24', '2026-04-24', '2026-05-01'],
            'next_payment_at' => '2026-05-08',
            'subscription_ends_at' => '2026-05-01',
            'dia_entrega' => 'sexta',
            'ciclo_entrega' => 'semanal',
        ]);
        $order->setRelation('preparacaoItems', collect());

        $entregas = $order->entregasSubscricao();

        $this->assertSame(4, $entregas['total']);
        $this->assertSame(4, $entregas['feitas']);
        $this->assertSame(0, $entregas['por_realizar']);
        $this->assertNull($entregas['proxima']);
        $this->assertNull($order->proximaEncomendaSubscricao());

        Carbon::setTestNow();
    }

    public function test_subscricao_quinzenal_postpone_keeps_four_deliveries(): void
    {
        Carbon::setTestNow('2026-04-09 10:00:00');

        $order = new WooOrder([
            'first_delivery_at' => '2026-04-08',
            'delivery_dates' => [],
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'quinzenal',
        ]);
        $order->setRelation('preparacaoItems', collect([
            new PreparacaoItem([
                'data_preparacao' => '2026-04-08',
                'feito' => true,
            ]),
        ]));

        $order->adiarProximaEntregaPara('2026-04-23');
        $order->setRelation('preparacaoItems', collect([
            new PreparacaoItem([
                'data_preparacao' => '2026-04-08',
                'feito' => true,
            ]),
        ]));

        $entregas = $order->entregasSubscricao();

        $this->assertSame(['2026-04-08', '2026-04-23', '2026-05-06', '2026-05-20'], $order->delivery_dates);
        $this->assertSame(4, $entregas['total']);
        $this->assertSame(1, $entregas['feitas']);
        $this->assertSame(3, $entregas['por_realizar']);
        $this->assertSame('2026-04-23', $entregas['proxima']);

        Carbon::setTestNow();
    }
}
