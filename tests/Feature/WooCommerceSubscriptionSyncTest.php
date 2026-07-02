<?php

namespace Tests\Feature;

use App\Models\WooOrder;
use App\Services\WooCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceSubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_sync_calculates_first_delivery_for_subscription_without_explicit_date(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
            'woocommerce.per_page' => 50,
            'woocommerce.sync_subscriptions' => true,
        ]);

        Http::fake([
            'example.test/wp-json/wc/v3/orders*' => Http::response([], 200),
            'example.test/wp-json/wc/v3/subscriptions*' => Http::response([
                [
                    'id' => 123,
                    'status' => 'active',
                    'date_created' => '2026-06-02T12:00:00',
                    'total' => '20.00',
                    'billing' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva',
                    ],
                    'line_items' => [
                        ['name' => 'Subscricao Cabaz Pequeno', 'quantity' => 1],
                    ],
                    'meta_data' => [
                        ['key' => '_hdm_dia_entrega', 'value' => 'quarta'],
                    ],
                ],
            ], 200),
        ]);

        app(WooCommerceService::class)->sync();

        $this->assertDatabaseHas('woo_orders', [
            'woo_id' => 123,
            'source_type' => 'subscription',
            'dia_entrega' => 'quarta',
            'first_delivery_at' => '2026-06-03',
        ]);
    }

    public function test_sync_removes_completed_orders_from_local_cache(): void
    {
        config([
            'woocommerce.url' => 'https://example.test',
            'woocommerce.key' => 'ck_test',
            'woocommerce.secret' => 'cs_test',
            'woocommerce.per_page' => 50,
            'woocommerce.sync_subscriptions' => false,
        ]);

        $completed = WooOrder::factory()->create([
            'woo_id' => 321,
            'source_type' => 'order',
            'status' => 'processing',
        ]);

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (($query['status'] ?? null) === 'completed') {
                return Http::response([
                    ['id' => 321, 'status' => 'completed'],
                ], 200);
            }

            return Http::response([], 200);
        });

        $result = app(WooCommerceService::class)->sync();

        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('woo_orders', ['id' => $completed->id]);
    }
}
