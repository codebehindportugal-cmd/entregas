<?php

namespace Tests\Unit;

use App\Services\WooCommerceService;
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

    private function payloadFromWooOrder(array $order): array
    {
        $method = new ReflectionMethod(WooCommerceService::class, 'payload');
        $method->setAccessible(true);

        return $method->invoke(app(WooCommerceService::class), $order, 'order');
    }
}
