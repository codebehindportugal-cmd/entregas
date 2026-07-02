<?php

namespace Tests\Feature;

use App\Models\WooOrder;
use App\Models\WooProduct;
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
                'coupon_lines' => [
                    ['code' => 'DESCONTO10', 'discount' => '10.00'],
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
                && $payload['coupon_lines'] === [
                    ['code' => 'DESCONTO10'],
                ]
                && in_array(['key' => '_hdm_publicada_de', 'value' => 123], $payload['meta_data'], true);
        });

        $this->assertSame('https://example.test/checkout/order-pay/456/?pay_for_order=true&key=wc_order_test', $result['payment_url']);
        $this->assertDatabaseHas('woo_orders', [
            'woo_id' => 456,
            'source_type' => 'order',
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

    public function test_create_pending_order_refreshes_original_order_when_local_product_ids_are_missing(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        $order = WooOrder::factory()->create([
            'woo_id' => 123,
            'billing_name' => 'Maria Silva',
            'billing_email' => 'maria@example.test',
            'raw_payload' => [
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                ],
                'line_items' => [],
            ],
        ]);

        Http::fake([
            'example.test/wp-json/wc/v3/orders/123' => Http::response([
                'id' => 123,
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'email' => 'maria@example.test',
                ],
                'line_items' => [
                    ['name' => 'Cabaz Pequeno', 'quantity' => 1, 'product_id' => 14383],
                ],
            ], 200),
            'example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 456,
                'status' => 'pending',
                'total' => '35.50',
                'date_created' => '2026-05-07T10:00:00',
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'email' => 'maria@example.test',
                ],
                'line_items' => [
                    ['name' => 'Cabaz Pequeno', 'quantity' => 1, 'product_id' => 14383],
                ],
            ], 201),
        ]);

        app(WooCommerceService::class)->createPendingOrderFrom($order);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/wp-json/wc/v3/orders')
                && data_get($payload, 'line_items.0.product_id') === 14383
                && data_get($payload, 'line_items.0.quantity') === 1;
        });
    }

    public function test_create_pending_order_uses_selected_woocommerce_products_and_manual_coupons(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        $product = WooProduct::create([
            'woo_id' => 987,
            'name' => 'Cabaz manual',
            'type' => 'simple',
            'status' => 'publish',
            'stock_status' => 'instock',
            'purchasable' => true,
            'em_epoca' => true,
            'disponivel_compra' => true,
        ]);

        $order = WooOrder::factory()->create([
            'woo_id' => 123,
            'billing_name' => 'Maria Silva',
            'billing_phone' => '910000000',
            'billing_email' => 'maria@example.test',
            'raw_payload' => [
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                ],
                'line_items' => [
                    ['name' => 'Valor manual', 'quantity' => 1],
                ],
            ],
        ]);

        Http::fake([
            'example.test/wp-json/wc/v3/orders/123' => Http::response([], 404),
            'example.test/wp-json/wc/v3/subscriptions/123' => Http::response([], 404),
            'example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 456,
                'status' => 'pending',
                'total' => '35.50',
                'date_created' => '2026-05-07T10:00:00',
                'billing' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Silva',
                    'email' => 'maria@example.test',
                ],
                'line_items' => [
                    ['name' => 'Cabaz manual', 'quantity' => 3, 'product_id' => 987],
                ],
            ], 201),
        ]);

        app(WooCommerceService::class)->createPendingOrderFrom($order, [
            'products' => [
                ['woo_product_id' => $product->id, 'quantity' => 3],
            ],
            'coupon_codes' => "MANUAL10\nFRESCO",
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/wp-json/wc/v3/orders')
                && data_get($payload, 'line_items.0.product_id') === 987
                && data_get($payload, 'line_items.0.quantity') === 3
                && collect(data_get($payload, 'coupon_lines', []))->pluck('code')->all() === ['MANUAL10', 'FRESCO'];
        });
    }

    public function test_create_pending_order_without_existing_profile_posts_customer_products_and_coupons(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        $product = WooProduct::create([
            'woo_id' => 654,
            'name' => 'Cabaz novo',
            'type' => 'simple',
            'status' => 'publish',
            'stock_status' => 'instock',
            'purchasable' => true,
            'em_epoca' => true,
            'disponivel_compra' => true,
        ]);

        Http::fake([
            'example.test/wp-json/wc/v3/orders' => Http::response([
                'id' => 789,
                'status' => 'pending',
                'total' => '22.00',
                'date_created' => '2026-05-07T10:00:00',
                'payment_url' => 'https://example.test/pay/789',
                'billing' => [
                    'first_name' => 'Cliente',
                    'last_name' => 'Novo',
                    'email' => 'novo@example.test',
                    'phone' => '910000000',
                ],
                'line_items' => [
                    ['name' => 'Cabaz novo', 'quantity' => 2, 'product_id' => 654],
                ],
            ], 201),
        ]);

        $result = app(WooCommerceService::class)->createPendingOrder([
            'billing_name' => 'Cliente Novo',
            'billing_phone' => '910000000',
            'billing_email' => 'novo@example.test',
            'billing_address_1' => 'Rua Nova 1',
            'billing_city' => 'Lisboa',
            'products' => [
                ['woo_product_id' => $product->id, 'quantity' => 2],
            ],
            'coupon_codes' => ['NOVO10'],
            'dia_entrega' => 'quarta',
            'scheduled_delivery_at' => '2026-05-13',
        ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && data_get($payload, 'billing.email') === 'novo@example.test'
                && data_get($payload, 'line_items.0.product_id') === 654
                && data_get($payload, 'line_items.0.quantity') === 2
                && collect(data_get($payload, 'coupon_lines', []))->pluck('code')->all() === ['NOVO10'];
        });
        $this->assertSame('https://example.test/pay/789', $result['payment_url']);
        $this->assertDatabaseHas('woo_orders', [
            'woo_id' => 789,
            'billing_email' => 'novo@example.test',
            'status' => 'pending',
        ]);
    }
}
