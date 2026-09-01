<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RenovacaoSubscricaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();

        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeWoo(): void
    {
        Http::fake([
            'example.test/*' => Http::response([
                'id' => 456,
                'status' => 'pending',
                'total' => '35.50',
                'date_created' => '2026-09-02T10:00:00',
                'payment_url' => 'https://example.test/checkout/order-pay/456/?pay_for_order=true',
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'email' => 'maria@example.test',
                    'phone' => '910000000',
                ],
                'line_items' => [
                    ['name' => 'Cabaz medio', 'quantity' => 1, 'product_id' => 10],
                ],
            ], 201),
        ]);
    }

    private function subscricao(array $atributos = []): WooOrder
    {
        return WooOrder::factory()->create(array_merge([
            'woo_id' => 123,
            'source_type' => 'subscription',
            'status' => 'active',
            'billing_name' => 'Maria Silva',
            'billing_phone' => '910000000',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
            // Ciclo de 4 entregas: 12/08, 19/08, 26/08 e 02/09.
            'first_delivery_at' => '2026-08-12',
            'delivery_dates' => [],
            'subscription_ends_at' => null,
            'renovacao_automatica' => true,
            'raw_payload' => [
                'billing' => ['first_name' => 'Maria', 'last_name' => 'Silva'],
                'shipping' => ['address_1' => 'Rua Teste 1', 'city' => 'Lisboa'],
                'line_items' => [
                    ['product_id' => 10, 'variation_id' => 0, 'quantity' => 1],
                ],
            ],
        ], $atributos));
    }

    public function test_cria_a_encomenda_de_renovacao_no_dia_da_ultima_entrega(): void
    {
        Carbon::setTestNow('2026-09-02 07:00:00');
        $this->fakeWoo();

        $subscricao = $this->subscricao();

        $this->assertSame('2026-09-02', $subscricao->ultimaEntregaDoCiclo());

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        $subscricao->refresh();

        $this->assertSame('2026-09-02', $subscricao->renovada_em->toDateString());
        $this->assertNotNull($subscricao->renovacao_woo_order_id);

        $nova = $subscricao->renovacaoWooOrder();

        $this->assertSame(456, $nova->woo_id);
        $this->assertStringContainsString('order-pay/456', (string) $nova->paymentUrl());
        // O link de pagamento vai na mensagem de WhatsApp para o cliente.
        $this->assertStringContainsString('wa.me/351910000000', (string) $nova->whatsappPagamentoUrl());
    }

    public function test_nao_renova_duas_vezes(): void
    {
        Carbon::setTestNow('2026-09-02 07:00:00');
        $this->fakeWoo();

        $subscricao = $this->subscricao();

        $this->artisan('subscricoes:renovar')->assertSuccessful();
        $this->artisan('subscricoes:renovar')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(2, WooOrder::count());
    }

    public function test_nao_renova_a_meio_do_ciclo(): void
    {
        Carbon::setTestNow('2026-08-26 07:00:00');
        $this->fakeWoo();

        $this->subscricao();

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull(WooOrder::first()->renovada_em);
    }

    public function test_nao_renova_subscricao_em_pausa(): void
    {
        Carbon::setTestNow('2026-09-02 07:00:00');
        $this->fakeWoo();

        $this->subscricao([
            'pausada_em' => '2026-08-20',
            'pausada_ate' => '2026-09-30',
        ]);

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_nao_renova_sem_a_opcao_ligada(): void
    {
        Carbon::setTestNow('2026-09-02 07:00:00');
        $this->fakeWoo();

        $this->subscricao(['renovacao_automatica' => false]);

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_nao_renova_subscricao_antiga_fora_da_janela(): void
    {
        Carbon::setTestNow('2026-12-01 07:00:00');
        $this->fakeWoo();

        $this->subscricao();

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_pagina_de_renovacoes_lista_o_que_falta_enviar(): void
    {
        Carbon::setTestNow('2026-09-02 07:00:00');
        $this->fakeWoo();

        $admin = User::factory()->admin()->create();
        $subscricao = $this->subscricao();

        $this->artisan('subscricoes:renovar')->assertSuccessful();

        $this->actingAs($admin)
            ->get(route('renovacoes.index'))
            ->assertOk()
            ->assertSee('Maria Silva');

        $this->actingAs($admin)
            ->put(route('renovacoes.enviada', $subscricao))
            ->assertRedirect();

        $this->assertNotNull($subscricao->fresh()->renovacao_enviada_em);
    }

    public function test_gravar_o_perfil_alinha_a_primeira_entrega_com_o_dia_de_entrega(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->subscricao(['renovacao_automatica' => false]);

        $this->actingAs($admin)->put(route('encomendas.profile.update', $order), [
            'source_type' => 'subscription',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'quinzenal',
            // 10/08 e uma segunda: tem de passar para quarta, 12/08.
            'first_delivery_at' => '2026-08-10',
        ])->assertRedirect();

        $order->refresh();

        $this->assertSame('2026-08-12', $order->first_delivery_at->toDateString());
        $this->assertSame([], $order->delivery_dates);
    }
}
