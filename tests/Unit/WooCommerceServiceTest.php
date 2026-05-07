<?php

namespace Tests\Unit;

use App\Services\WooCommerceService;
use Illuminate\Support\Facades\Http;
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
}
