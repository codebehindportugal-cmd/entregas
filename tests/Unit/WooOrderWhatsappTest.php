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

    public function test_moloni_document_uses_last_positive_document_id(): void
    {
        config(['woocommerce.url' => 'https://loja.test']);

        $order = new WooOrder([
            'woo_id' => 123,
            'raw_payload' => [
                'meta_data' => [
                    ['key' => '_moloni_sent', 'value' => '-1'],
                    ['key' => '_moloni_sent', 'value' => '456'],
                    ['key' => '_moloni_sent', 'value' => '789'],
                ],
            ],
        ]);

        $this->assertSame([456, 789], $order->moloniDocumentIds());
        $this->assertSame(789, $order->moloniDocumentId());
        $this->assertSame('https://loja.test/wp-admin/admin.php?page=moloni&action=getInvoice&id=789', $order->moloniDocumentUrl());
        $this->assertSame('https://loja.test/wp-admin/admin.php?page=moloni&action=downloadDocument&id=789', $order->moloniDownloadDocumentUrl());
    }

    public function test_moloni_generate_document_url_points_to_woocommerce_order(): void
    {
        config(['woocommerce.url' => 'https://loja.test']);

        $order = new WooOrder([
            'woo_id' => 123,
            'raw_payload' => ['meta_data' => []],
        ]);

        $this->assertNull($order->moloniDocumentId());
        $this->assertSame('https://loja.test/wp-admin/admin.php?page=moloni&action=genInvoice&id=123', $order->moloniGenerateDocumentUrl());
    }

    public function test_invoice_whatsapp_message_is_available_after_moloni_document_exists(): void
    {
        config(['woocommerce.url' => 'https://loja.test']);

        $order = new WooOrder([
            'woo_id' => 123,
            'billing_name' => 'John',
            'billing_phone' => '912345678',
            'customer_language' => 'en',
            'raw_payload' => [
                'order_key' => 'wc_order_test',
                'meta_data' => [
                    ['key' => '_moloni_sent', 'value' => '789'],
                ],
            ],
        ]);
        $order->id = 1;

        $message = $this->messageFromUrl($order->whatsappFaturaUrl());

        $this->assertStringContainsString('Hi John!', $message);
        $this->assertStringContainsString('Your Horta da Maria invoice is available here:', $message);
        $this->assertStringContainsString('/faturas/', $message);
        $this->assertStringContainsString('signature=', $message);
    }

    private function messageFromUrl(?string $url): string
    {
        $this->assertNotNull($url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query['text'] ?? '');
    }
}
