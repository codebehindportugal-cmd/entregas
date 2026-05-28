<?php

namespace Tests\Unit;

use App\Models\WooOrder;
use Tests\TestCase;

class WooOrderWhatsappTest extends TestCase
{
    public function test_payment_whatsapp_message_uses_english_for_english_customers(): void
    {
        $order = new WooOrder([
            'billing_name' => 'John',
            'billing_phone' => '912345678',
            'raw_payload' => [
                'payment_url' => 'https://example.test/pay',
                'meta_data' => [
                    ['key' => 'trp_language', 'value' => 'en_US'],
                ],
            ],
        ]);

        $message = $this->messageFromUrl($order->whatsappPagamentoUrl());

        $this->assertStringContainsString('Hi John!', $message);
        $this->assertStringContainsString('Your Horta da Maria order is ready.', $message);
        $this->assertStringContainsString('Thank you!', $message);
    }

    public function test_renewal_whatsapp_message_uses_portuguese_by_default(): void
    {
        $order = new WooOrder([
            'billing_name' => 'Maria',
            'billing_phone' => '912345678',
            'raw_payload' => [
                'meta_data' => [
                    ['key' => 'trp_language', 'value' => 'pt_PT'],
                ],
            ],
        ]);

        $message = $this->messageFromUrl($order->whatsappRenovacaoUrl());

        $this->assertStringContainsString('Ola Maria!', $message);
        $this->assertStringContainsString('Deseja que enviemos?', $message);
    }

    public function test_manual_language_preference_overrides_woocommerce_payload(): void
    {
        $order = new WooOrder([
            'billing_name' => 'John',
            'billing_phone' => '912345678',
            'customer_language' => 'en',
            'raw_payload' => [
                'meta_data' => [
                    ['key' => 'trp_language', 'value' => 'pt_PT'],
                ],
            ],
        ]);

        $message = $this->messageFromUrl($order->whatsappRenovacaoUrl());

        $this->assertStringContainsString('Hi John!', $message);
        $this->assertStringContainsString('Shall we send it?', $message);
    }

    private function messageFromUrl(?string $url): string
    {
        $this->assertNotNull($url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query['text'] ?? '');
    }
}
