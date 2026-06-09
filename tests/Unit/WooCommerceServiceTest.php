<?php

namespace Tests\Unit;

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
}
