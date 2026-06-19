<?php

namespace Tests\Unit;

use App\Models\WooOrder;
use App\Models\WooProduct;
use App\Services\WooCommerceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class WooCommerceServiceTest extends TestCase
{
    public function test_fetch_orders_never_requests_completed_status(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
            'woocommerce.statuses' => 'processing,completed,wc-completed,pending',
            'woocommerce.per_page' => 50,
        ]);

        Http::fake([
            'example.test/*' => Http::response([], 200),
        ]);

        app(WooCommerceService::class)->fetchOrders();

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['status'] ?? null) === 'processing,pending,subscricao';
        });
    }

    public function test_payload_prefers_explicit_delivery_date_over_profile_day(): void
    {
        $payload = $this->payloadFromWooOrder([
            'id' => 123,
            'status' => 'processing',
            'date_created' => '2026-05-14T10:00:00',
            'billing' => ['first_name' => 'Maria', 'last_name' => 'Silva'],
            'total' => '20.00',
            'line_items' => [],
            'meta_data' => [
                ['key' => '_hdm_dia_entrega', 'value' => 'quarta'],
                ['key' => '_hdm_data_entrega', 'value' => '2026-05-30'],
            ],
        ]);

        $this->assertSame('quarta', $payload['dia_entrega']);
        $this->assertSame('2026-05-30', $payload['scheduled_delivery_at']);
    }

    public function test_payload_reads_delivery_date_from_line_item_metadata(): void
    {
        $payload = $this->payloadFromWooOrder([
            'id' => 124,
            'status' => 'processing',
            'date_created' => '2026-05-14T10:00:00',
            'billing' => [],
            'total' => '20.00',
            'meta_data' => [
                ['key' => '_hdm_dia_entrega', 'value' => 'quarta'],
            ],
            'line_items' => [
                [
                    'name' => 'Cabaz Pequeno',
                    'quantity' => 1,
                    'meta_data' => [
                        ['key' => 'Data de entrega', 'value' => '2026-05-30'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('2026-05-30', $payload['scheduled_delivery_at']);
    }

    public function test_payload_normalizes_customer_language_from_woocommerce_metadata(): void
    {
        $payload = $this->payloadFromWooOrder([
            'id' => 125,
            'status' => 'processing',
            'date_created' => '2026-05-14T10:00:00',
            'billing' => [],
            'total' => '20.00',
            'line_items' => [],
            'meta_data' => [
                ['key' => 'trp_language', 'value' => 'en_US'],
            ],
        ]);

        $this->assertSame('en', $payload['customer_language']);
    }

    public function test_scheduled_delivery_uses_only_customer_day(): void
    {
        $this->assertSame(
            '2026-06-01',
            $this->scheduledDeliveryDate('2026-05-27 10:00:00', 'segunda')
        );
    }

    public function test_scheduled_delivery_accepts_orders_until_noon_previous_day(): void
    {
        $this->assertSame(
            '2026-06-03',
            $this->scheduledDeliveryDate('2026-06-02 12:00:00', 'quarta')
        );

        $this->assertSame(
            '2026-06-10',
            $this->scheduledDeliveryDate('2026-06-02 12:01:00', 'quarta')
        );
    }

    public function test_scheduled_delivery_returns_null_without_delivery_day(): void
    {
        $this->assertNull($this->scheduledDeliveryDate('2026-05-27 10:00:00', null));
    }

    public function test_missing_subscription_first_delivery_is_calculated_from_order_date_and_delivery_day(): void
    {
        $payload = $this->fillMissingSubscriptionFirstDelivery([
            'source_type' => 'subscription',
            'ordered_at' => Carbon::parse('2026-06-02 12:00:00'),
            'first_delivery_at' => null,
            'dia_entrega' => 'quarta',
        ]);

        $this->assertSame('2026-06-03', $payload['first_delivery_at']);
    }

    public function test_payload_reads_subscription_start_end_and_next_payment_from_woocommerce_fields(): void
    {
        $payload = $this->payloadFromWooOrder([
            'id' => 126,
            'status' => 'active',
            'date_created' => '2026-05-01T10:00:00',
            'start_date' => '2026-05-06T00:00:00',
            'next_payment_date' => '2026-06-03T00:00:00',
            'end_date' => '2026-05-27T00:00:00',
            'billing' => [],
            'total' => '80.00',
            'line_items' => [
                ['name' => 'Subscricao Cabaz Pequeno', 'quantity' => 1],
            ],
            'meta_data' => [],
        ]);

        $this->assertSame('subscription', $payload['source_type']);
        $this->assertSame('2026-05-06', $payload['first_delivery_at']);
        $this->assertSame('2026-06-03', $payload['next_payment_at']);
        $this->assertSame('2026-05-27', $payload['subscription_ends_at']);
    }

    public function test_update_product_from_local_disables_quantity_stock_management_for_manual_availability(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
        ]);

        Http::fake([
            'example.test/*' => Http::response([
                'id' => 321,
                'name' => 'Cabaz Teste',
                'regular_price' => '12.00',
                'sale_price' => '',
                'status' => 'publish',
                'stock_status' => 'instock',
                'purchasable' => true,
            ], 200),
        ]);

        $product = new WooProduct([
            'woo_id' => 321,
            'name' => 'Cabaz Teste',
            'regular_price' => 12,
            'status' => 'publish',
            'em_epoca' => true,
            'disponivel_compra' => true,
        ]);

        app(WooCommerceService::class)->updateProductFromLocal($product);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://example.test/wp-json/wc/v3/products/321'
                && $request['manage_stock'] === false
                && $request['stock_status'] === 'instock';
        });
    }

    public function test_product_payload_reads_availability_from_woocommerce_metadata(): void
    {
        $payload = $this->productPayloadFromWooProduct([
            'id' => 321,
            'name' => 'Cabaz Teste',
            'status' => 'publish',
            'stock_status' => 'instock',
            'purchasable' => true,
            'meta_data' => [
                ['key' => '_hdm_em_epoca', 'value' => '0'],
                ['key' => '_hdm_disponivel_compra', 'value' => '1'],
                ['key' => '_hdm_epoca', 'value' => 'Verao'],
            ],
        ]);

        $this->assertFalse($payload['em_epoca']);
        $this->assertTrue($payload['disponivel_compra']);
        $this->assertSame('Verao', $payload['epoca']);
    }

    public function test_product_payload_falls_back_to_woocommerce_status_when_metadata_is_missing(): void
    {
        $payload = $this->productPayloadFromWooProduct([
            'id' => 322,
            'name' => 'Produto Rascunho',
            'status' => 'draft',
            'stock_status' => 'instock',
            'purchasable' => true,
            'meta_data' => [],
        ]);

        $this->assertFalse($payload['em_epoca']);
        $this->assertFalse($payload['disponivel_compra']);
    }

    public function test_sync_preserves_local_customer_information_when_woocommerce_payload_is_blank(): void
    {
        $order = new WooOrder([
            'billing_name' => 'Cliente Guardado',
            'billing_phone' => '912345678',
            'billing_email' => 'cliente@example.test',
        ]);
        $order->exists = true;

        $payload = $this->preserveLocalScheduling($order, [
            'billing_name' => null,
            'billing_phone' => null,
            'billing_email' => null,
            'status' => 'processing',
        ]);

        $this->assertSame('Cliente Guardado', $payload['billing_name']);
        $this->assertSame('912345678', $payload['billing_phone']);
        $this->assertSame('cliente@example.test', $payload['billing_email']);
    }

    public function test_sync_preserves_local_subscription_dates_when_woocommerce_payload_is_blank(): void
    {
        $order = new WooOrder([
            'source_type' => 'subscription',
            'first_delivery_at' => '2026-06-03',
            'next_payment_at' => '2026-07-01',
            'subscription_ends_at' => '2026-06-24',
            'delivery_dates' => ['2026-06-03', '2026-06-10', '2026-06-17', '2026-06-24'],
            'cancelled_delivery_dates' => ['2026-06-10'],
        ]);
        $order->exists = true;

        $payload = $this->preserveLocalScheduling($order, [
            'source_type' => 'subscription',
            'first_delivery_at' => null,
            'next_payment_at' => null,
            'subscription_ends_at' => null,
            'delivery_dates' => [],
            'cancelled_delivery_dates' => [],
        ]);

        $this->assertSame('2026-06-03', $payload['first_delivery_at']);
        $this->assertSame('2026-07-01', $payload['next_payment_at']);
        $this->assertSame('2026-06-24', $payload['subscription_ends_at']);
        $this->assertSame(['2026-06-03', '2026-06-10', '2026-06-17', '2026-06-24'], $payload['delivery_dates']);
        $this->assertSame(['2026-06-10'], $payload['cancelled_delivery_dates']);
    }

    private function payloadFromWooOrder(array $order): array
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'payload');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), $order, 'order');
    }

    private function scheduledDeliveryDate(string $orderedAt, ?string $diaEntrega): ?string
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'scheduledDeliveryDate');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), Carbon::parse($orderedAt), $diaEntrega);
    }

    private function fillMissingSubscriptionFirstDelivery(array $payload): array
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'fillMissingSubscriptionFirstDelivery');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), $payload);
    }

    private function productPayloadFromWooProduct(array $product): array
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'productPayload');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), $product);
    }

    private function preserveLocalScheduling(WooOrder $order, array $payload): array
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'preserveLocalScheduling');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), $order, $payload);
    }
}
