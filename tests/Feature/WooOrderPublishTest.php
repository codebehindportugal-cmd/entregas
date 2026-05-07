<?php

namespace Tests\Feature;

use App\Models\WooOrder;
use App\Services\WooCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooOrderPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_create_pending_order_from_local_order_posts_same_customer_and_products(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        $order = WooOrder::factory()->create([
            'woo_id' => 123,
            'billing_name' => 'Maria Silva',
            'billing_phone' => '910000000',
            'billing_email' => 'maria@example.test',
            'customer_notes' => 'Deixar na portaria.',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
            'preferences_text' => 'Sem banana.',
            'raw_payload' => [
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'address_1' => 'Rua Teste 1',
                    'city' => 'Lisboa',
                ],
                'shipping' => [
                    'address_1' => 'Rua Teste 1',
                    'city' => 'Lisboa',
                ],
                'line_items' => [
                    ['product_id' => 10, 'variation_id' => 0, 'quantity' => 2],
                    ['product_id' => 11, 'variation_id' => 12, 'quantity' => 1],
                ],
            ],
        ]);

        Http::fake([
            'example.test/*' => Http::response([
                'id' => 456,
                'status' => 'pending',
                'total' => '35.50',
                'date_created' => '2026-05-07T10:00:00',
                'payment_url' => 'https://example.test/checkout/order-pay/456/?pay_for_order=true&key=wc_order_test',
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'email' => 'maria@example.test',
                    'phone' => '910000000',
                ],
                'line_items' => [
                    ['name' => 'Cabaz', 'quantity' => 2, 'product_id' => 10],
                    ['name' => 'Ovos', 'quantity' => 1, 'product_id' => 11, 'variation_id' => 12],
                ],
            ], 201),
        ]);

        $result = app(WooCommerceService::class)->createPendingOrderFrom($order);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $payload['status'] === 'pending'
                && $payload['billing']['email'] === 'maria@example.test'
                && $payload['billing']['address_1'] === 'Rua Teste 1'
                && $payload['shipping']['city'] === 'Lisboa'
                && $payload['line_items'] === [
                    ['product_id' => 10, 'quantity' => 2],
                    ['product_id' => 11, 'variation_id' => 12, 'quantity' => 1],
                ]
                && in_array(['key' => '_hdm_publicada_de', 'value' => 123], $payload['meta_data'], true);
        });

        $this->assertSame('https://example.test/checkout/order-pay/456/?pay_for_order=true&key=wc_order_test', $result['payment_url']);
        $this->assertDatabaseHas('woo_orders', [
            'woo_id' => 456,
            'status' => 'pending',
            'billing_email' => 'maria@example.test',
        ]);
    }

    public function test_create_pending_order_omits_invalid_customer_email(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        $order = WooOrder::factory()->create([
            'woo_id' => 123,
            'billing_name' => 'Cliente Sem Email',
            'billing_phone' => '910000000',
            'billing_email' => '',
            'raw_payload' => [
                'billing' => [
                    'first_name' => 'Cliente',
                    'last_name' => 'Sem Email',
                    'email' => 'sem-email',
                    'phone' => '',
                ],
                'line_items' => [
                    ['product_id' => 10, 'quantity' => 1],
                ],
            ],
        ]);

        Http::fake([
            'example.test/*' => Http::response([
                'id' => 456,
                'status' => 'pending',
                'total' => '10.00',
                'date_created' => '2026-05-07T10:00:00',
                'billing' => [
                    'first_name' => 'Cliente',
                    'last_name' => 'Sem Email',
                    'phone' => '910000000',
                ],
                'line_items' => [
                    ['name' => 'Cabaz', 'quantity' => 1, 'product_id' => 10],
                ],
            ], 201),
        ]);

        app(WooCommerceService::class)->createPendingOrderFrom($order);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && ! array_key_exists('email', $payload['billing'])
                && $payload['billing']['phone'] === '910000000';
        });
    }
}
