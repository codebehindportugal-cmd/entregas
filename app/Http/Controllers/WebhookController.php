<?php

namespace App\Http\Controllers;

use App\Services\WooCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function woocommerce(Request $request, WooCommerceService $service): JsonResponse
    {
        $secret = config('woocommerce.webhook_secret');
        $signature = $request->header('X-WC-Webhook-Signature');

        if (blank($secret)) {
            return response()->json(['message' => 'WooCommerce webhook secret is not configured.'], 500);
        }

        if (blank($signature) || ! $this->validSignature($request->getContent(), (string) $secret, (string) $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $result = $service->sync();

        return response()->json([
            'message' => 'WooCommerce synchronized.',
            'result' => $result,
        ]);
    }

    private function validSignature(string $payload, string $secret, string $signature): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expected, $signature);
    }
}
