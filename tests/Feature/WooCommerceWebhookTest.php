<?php

namespace Tests\Feature;

use App\Services\WooCommerceService;
use Mockery\MockInterface;
use Tests\TestCase;

class WooCommerceWebhookTest extends TestCase
{
    public function test_woocommerce_webhook_syncs_with_valid_signature(): void
    {
        config(['woocommerce.webhook_secret' => 'webhook-secret']);

        $payload = '{"id":123,"topic":"order.created"}';

        $this->mock(WooCommerceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')
                ->once()
                ->andReturn([
                    'fetched' => 1,
                    'orders' => 1,
                    'subscriptions' => 0,
                    'created' => 1,
                    'updated' => 0,
                ]);
        });

        $response = $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $this->signature($payload, 'webhook-secret'),
        ], $payload);

        $response
            ->assertOk()
            ->assertJsonPath('result.fetched', 1);
    }

    public function test_woocommerce_webhook_rejects_invalid_signature(): void
    {
        config(['woocommerce.webhook_secret' => 'webhook-secret']);

        $this->mock(WooCommerceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sync');
        });

        $response = $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => 'invalid',
        ], '{"id":123}');

        $response->assertUnauthorized();
    }

    private function signature(string $payload, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }
}
