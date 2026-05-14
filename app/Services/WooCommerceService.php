<?php

namespace App\Services;

use App\Models\WooOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WooCommerceService
{
    public function sync(): array
    {
        $orders = collect($this->fetchOrders())
            ->reject(fn (array $order) => (string) Arr::get($order, 'status') === 'completed')
            ->unique(fn (array $order) => (int) $order['id'])
            ->values()
            ->all();
        $created = 0;
        $updated = 0;

        foreach ($orders as $order) {
            $model = WooOrder::updateOrCreate(
                ['woo_id' => (int) $order['id']],
                $this->payload($order, 'order')
            );

            $model->wasRecentlyCreated ? $created++ : $updated++;
        }

        $subscriptions = [];

        if (config('woocommerce.sync_subscriptions')) {
            $subscriptions = $this->fetchSubscriptions();

            foreach ($subscriptions as $subscription) {
                $model = WooOrder::updateOrCreate(
                    ['woo_id' => (int) $subscription['id']],
                    $this->payload($subscription, 'subscription')
                );

                $model->wasRecentlyCreated ? $created++ : $updated++;
            }
        }

        return [
            'fetched' => count($orders) + count($subscriptions),
            'orders' => count($orders),
            'subscriptions' => count($subscriptions),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function fetchOrders(): array
    {
        $url = rtrim((string) config('woocommerce.url'), '/');
        $key = config('woocommerce.key');
        $secret = config('woocommerce.secret');

        if (blank($url) || blank($key) || blank($secret)) {
            throw new RuntimeException('Configura as variaveis WOOCOMMERCE_URL, WOOCOMMERCE_KEY e WOOCOMMERCE_SECRET no .env.');
        }

        $response = $this->client()
            ->get("{$url}/wp-json/wc/v3/orders", [
                'status' => $this->orderStatuses(),
                'per_page' => config('woocommerce.per_page'),
                'orderby' => 'date',
                'order' => 'desc',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Erro WooCommerce: '.$response->status().' - '.$response->body());
        }

        return $response->json();
    }

    public function fetchSubscriptions(): array
    {
        $url = rtrim((string) config('woocommerce.url'), '/');

        $response = $this->client()
            ->get("{$url}/wp-json/wc/v3/subscriptions", [
                'status' => config('woocommerce.subscription_statuses'),
                'per_page' => config('woocommerce.per_page'),
                'orderby' => 'date',
                'order' => 'desc',
            ]);

        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new RuntimeException('Erro WooCommerce Subscriptions: '.$response->status().' - '.$response->body());
        }

        return $response->json();
    }

    public function createPendingOrderFrom(WooOrder $order): array
    {
        $url = rtrim((string) config('woocommerce.url'), '/');

        if (blank($url) || blank(config('woocommerce.key')) || blank(config('woocommerce.secret'))) {
            throw new RuntimeException('Configura as variaveis WOOCOMMERCE_URL, WOOCOMMERCE_KEY e WOOCOMMERCE_SECRET no .env.');
        }

        $payload = $this->pendingOrderPayload($order);

        $response = $this->client()
            ->post("{$url}/wp-json/wc/v3/orders", $payload);

        if ($response->failed()) {
            throw new RuntimeException('Erro ao publicar encomenda WooCommerce: '.$response->status().' - '.$response->body());
        }

        $wooOrder = $response->json();
        $model = WooOrder::updateOrCreate(
            ['woo_id' => (int) Arr::get($wooOrder, 'id')],
            $this->payload($wooOrder, 'order')
        );

        return [
            'order' => $model,
            'payment_url' => $this->paymentUrl($wooOrder),
        ];
    }

    public function markAsCompleted(WooOrder $order): WooOrder
    {
        $url = rtrim((string) config('woocommerce.url'), '/');

        if (blank($url) || blank(config('woocommerce.key')) || blank(config('woocommerce.secret'))) {
            throw new RuntimeException('Configura as variaveis WOOCOMMERCE_URL, WOOCOMMERCE_KEY e WOOCOMMERCE_SECRET no .env.');
        }

        $resource = $order->source_type === 'subscription' || in_array($order->status, ['subscricao', 'wc-subscricao'], true)
            ? 'subscriptions'
            : 'orders';

        $response = $this->client()
            ->put("{$url}/wp-json/wc/v3/{$resource}/{$order->woo_id}", [
                'status' => 'completed',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Erro ao concluir no WooCommerce: '.$response->status().' - '.$response->body());
        }

        $payload = $response->json();
        $order->update($this->payload($payload, $order->source_type));

        return $order->fresh();
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(config('woocommerce.key'), config('woocommerce.secret'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    private function pendingOrderPayload(WooOrder $order): array
    {
        $lineItems = collect(Arr::get($order->raw_payload ?? [], 'line_items', []))
            ->map(function (array $item): array {
                return array_filter([
                    'product_id' => (int) Arr::get($item, 'product_id'),
                    'variation_id' => (int) Arr::get($item, 'variation_id'),
                    'quantity' => max(1, (int) Arr::get($item, 'quantity', 1)),
                ], fn (mixed $value): bool => $value !== null && $value !== 0 && $value !== '');
            })
            ->filter(fn (array $item): bool => (int) ($item['product_id'] ?? 0) > 0)
            ->values()
            ->all();

        if ($lineItems === []) {
            throw new RuntimeException('Nao foi possivel publicar: a encomenda nao tem IDs de produto WooCommerce sincronizados.');
        }

        $billing = Arr::get($order->raw_payload ?? [], 'billing', []);
        [$firstName, $lastName] = $this->splitBillingName($order->billing_name);
        $billing['first_name'] = Arr::get($billing, 'first_name') ?: $firstName;
        $billing['last_name'] = Arr::get($billing, 'last_name') ?: $lastName;
        $billing['email'] = $order->billing_email ?: Arr::get($billing, 'email');
        $billing['phone'] = $order->billing_phone ?: Arr::get($billing, 'phone');
        $billing = $this->sanitizeBilling($billing);

        $payload = [
            'status' => 'pending',
            'billing' => $billing,
            'shipping' => Arr::get($order->raw_payload ?? [], 'shipping', []),
            'line_items' => $lineItems,
            'customer_note' => $order->customer_notes,
            'meta_data' => $this->pendingOrderMeta($order),
        ];

        $customerId = (int) Arr::get($order->raw_payload ?? [], 'customer_id');

        if ($customerId > 0) {
            $payload['customer_id'] = $customerId;
        }

        return $payload;
    }

    private function pendingOrderMeta(WooOrder $order): array
    {
        return collect([
            '_hdm_dia_entrega' => $order->dia_entrega,
            '_hdm_ciclo_entrega' => $order->ciclo_entrega,
            '_hdm_produtos_excluidos' => $order->preferences_text ?: $order->profile_preferences,
            '_hdm_publicada_de' => $order->woo_id,
        ])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value, string $key): array => ['key' => $key, 'value' => $value])
            ->values()
            ->all();
    }

    private function splitBillingName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    private function sanitizeBilling(array $billing): array
    {
        $billing = collect($billing)
            ->reject(fn (mixed $value): bool => blank($value))
            ->all();

        if (isset($billing['email']) && ! filter_var($billing['email'], FILTER_VALIDATE_EMAIL)) {
            unset($billing['email']);
        }

        return $billing;
    }

    private function paymentUrl(array $order): ?string
    {
        $paymentUrl = Arr::get($order, 'payment_url');

        if (filled($paymentUrl)) {
            return $paymentUrl;
        }

        $orderKey = Arr::get($order, 'order_key');
        $orderId = Arr::get($order, 'id');

        if (blank($orderKey) || blank($orderId)) {
            return null;
        }

        return rtrim((string) config('woocommerce.url'), '/')."/checkout/order-pay/{$orderId}/?pay_for_order=true&key={$orderKey}";
    }

    private function orderStatuses(): string
    {
        return collect(explode(',', (string) config('woocommerce.statuses')))
            ->map(fn (string $status) => trim($status))
            ->filter()
            ->reject(fn (string $status) => $status === 'completed' || $status === 'wc-completed')
            ->merge(['subscricao'])
            ->unique()
            ->implode(',');
    }

    private function payload(array $order, string $sourceType): array
    {
        $billing = Arr::get($order, 'billing', []);
        $metadata = collect(Arr::get($order, 'meta_data', []))->mapWithKeys(function (array $item): array {
            return [$item['key'] ?? '' => $item['value'] ?? null];
        });
        $sourceType = $this->detectSourceType($order, $sourceType);
        $orderedAt = $this->dateTimeOrNull(Arr::get($order, 'date_created'));
        $diaEntrega = $this->normalizeDiaEntrega(
            $metadata->get('_hdm_dia_entrega')
                ?: $metadata->get('_dia_entrega')
                ?: $metadata->get('_hdm_dias_entrega')
        );
        $deliveryDates = $this->deliveryDates($metadata->get('_hdm_datas_entrega'));
        $cancelledDeliveryDates = $this->deliveryDates($metadata->get('_hdm_datas_canceladas'));
        $subscriptionEndsAt = $this->dateOrNull($metadata->get('_hdm_fim_subscricao'));
        $rawPreferences = $metadata->get('_excluded_products') ?: $metadata->get('_hdm_produtos_excluidos');
        $preferencesText = $this->preferencesText($rawPreferences);
        $excludedProducts = $this->excludedProducts($rawPreferences);
        $cicloEntrega = $this->normalizeCicloEntrega([
            $metadata->get('_hdm_frequencia_entrega')
                ?: $metadata->get('_hdm_ciclo_entrega'),
            $metadata->get('_ciclo_entrega'),
            $metadata->get('_periodicidade_entrega'),
            $metadata->get('_subscription_period_interval'),
            Arr::get($order, 'billing_interval'),
            $metadata->get('_hdm_produtos_excluidos'),
            collect(Arr::get($order, 'line_items', []))->pluck('name')->implode(' '),
            $this->cycleFromDeliveryDates($deliveryDates),
        ]);

        return [
            'source_type' => $sourceType,
            'ordered_at' => $orderedAt,
            'status' => (string) Arr::get($order, 'status', 'unknown'),
            'total' => (float) Arr::get($order, 'total', 0),
            'billing_name' => trim(Arr::get($billing, 'first_name', '').' '.Arr::get($billing, 'last_name', '')) ?: null,
            'billing_phone' => Arr::get($billing, 'phone'),
            'billing_email' => Arr::get($billing, 'email'),
            'line_items' => collect(Arr::get($order, 'line_items', []))->map(fn (array $item): array => [
                'name' => Arr::get($item, 'name'),
                'quantity' => (int) Arr::get($item, 'quantity', 0),
            ])->values()->all(),
            'postponed_until' => $this->dateOrNull($metadata->get('_postponed_until')),
            'next_payment_at' => $this->dateOrNull(Arr::get($order, 'next_payment_date') ?: Arr::get($order, 'date_next_payment')),
            'first_delivery_at' => $this->dateOrNull($metadata->get('_hdm_data_primeira_entrega')),
            'delivery_dates' => $deliveryDates,
            'cancelled_delivery_dates' => $cancelledDeliveryDates,
            'subscription_ends_at' => $subscriptionEndsAt,
            'excluded_products' => $excludedProducts,
            'preferences_text' => $preferencesText,
            'dia_entrega' => $diaEntrega,
            'cabaz_tipo' => WooOrder::detectarCabazTipo(Arr::get($order, 'line_items', [])),
            'ciclo_entrega' => $cicloEntrega,
            'scheduled_delivery_at' => $sourceType === 'subscription' ? null : $this->scheduledDeliveryDate($orderedAt, $diaEntrega),
            'raw_payload' => $order,
            'synced_at' => now(),
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateTimeOrNull(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (blank($value)) {
            return [];
        }

        $value = str_replace(['Não consome:', 'Nao consome:', 'Não consome', 'Nao consome'], '', (string) $value);

        return collect(preg_split('/[,;\r\n]+/', $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function preferencesText(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->filter()->implode(', ');
        }

        if (blank($value)) {
            return null;
        }

        $text = trim((string) $value);
        $normalized = Str::of($text)->lower()->ascii()->trim()->toString();

        if (in_array($normalized, ['weekly', 'biweekly', 'semanal', 'quinzenal', '15 em 15 dias'], true)) {
            return null;
        }

        return preg_replace("/\r\n|\r|\n/", "\n", $text);
    }

    private function excludedProducts(mixed $value): array
    {
        $text = $this->preferencesText($value);

        if ($text === null) {
            return [];
        }

        if (! preg_match('/(?:não|nao)\s+(?:consome|preciso\s+de)\s*:?\s*(.+)$/isu', $text, $matches)) {
            return [];
        }

        $text = $matches[1];

        return collect(preg_split('/[,;\r\n.]+/', $text) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->reject(fn (string $item) => Str::of($item)->lower()->ascii()->contains(['weekly', 'biweekly', 'quinzenal', '15 em 15']))
            ->values()
            ->all();
    }

    private function normalizeDiaEntrega(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = Str::of((string) $value)->lower()->ascii()->trim()->toString();

        return match (true) {
            str_contains($normalized, 'segunda') => 'segunda',
            str_contains($normalized, 'quarta') => 'quarta',
            str_contains($normalized, 'sabado') => 'sabado',
            default => $normalized,
        };
    }

    private function normalizeCicloEntrega(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $candidate) {
                if (blank($candidate)) {
                    continue;
                }

                $normalized = $this->normalizeCicloEntrega($candidate);

                if ($normalized === 'quinzenal') {
                    return 'quinzenal';
                }
            }

            return 'semanal';
        }

        if (blank($value)) {
            return 'semanal';
        }

        if (is_numeric($value)) {
            return (int) $value >= 2 ? 'quinzenal' : 'semanal';
        }

        $normalized = Str::of((string) $value)->lower()->ascii()->trim()->toString();

        return match (true) {
            str_contains($normalized, 'biweekly'),
            str_contains($normalized, 'bi-weekly'),
            str_contains($normalized, 'quinzenal'),
            str_contains($normalized, '15'),
            str_contains($normalized, '2 semanas'),
            str_contains($normalized, '2 semana'),
            str_contains($normalized, '14 dias'),
            $normalized === '2' => 'quinzenal',
            default => 'semanal',
        };
    }

    private function cycleFromDeliveryDates(array $dates): ?string
    {
        if (count($dates) < 2) {
            return null;
        }

        $diffs = collect($dates)
            ->sort()
            ->values()
            ->sliding(2)
            ->map(function ($pair): int {
                $pair = collect($pair)->values();

                return Carbon::parse($pair->get(0))->diffInDays(Carbon::parse($pair->get(1)));
            })
            ->filter(fn (int $days) => $days > 0);

        if ($diffs->isEmpty()) {
            return null;
        }

        return $diffs->avg() >= 13 ? 'quinzenal' : 'semanal';
    }

    private function detectSourceType(array $order, string $fallback): string
    {
        if ($fallback === 'subscription') {
            return 'subscription';
        }

        $status = (string) Arr::get($order, 'status');
        $subscriptionMetaKeys = [
            '_hdm_frequencia_entrega',
            '_hdm_datas_entrega',
            '_hdm_fim_subscricao',
            '_hdm_data_primeira_entrega',
        ];
        $hasSubscriptionMeta = collect(Arr::get($order, 'meta_data', []))
            ->contains(fn (array $item) => in_array($item['key'] ?? null, $subscriptionMetaKeys, true));
        $hasSubscriptionProduct = collect(Arr::get($order, 'line_items', []))
            ->contains(fn (array $item) => Str::of((string) Arr::get($item, 'name'))->lower()->ascii()->contains('subscricao'));

        return in_array($status, ['subscricao', 'wc-subscricao'], true) || $hasSubscriptionMeta || $hasSubscriptionProduct
            ? 'subscription'
            : 'order';
    }

    private function scheduledDeliveryDate(?Carbon $orderedAt, ?string $diaEntrega): ?string
    {
        if ($orderedAt === null) {
            return null;
        }

        $preferredDays = match ($diaEntrega) {
            'segunda' => [1, 3, 6],
            'quarta' => [3, 6],
            'sabado' => [6, 3],
            default => [1, 3, 6],
        };

        for ($offset = 0; $offset <= 14; $offset++) {
            $candidate = $orderedAt->copy()->startOfDay()->addDays($offset);

            if (! in_array($candidate->dayOfWeek, $preferredDays, true)) {
                continue;
            }

            $cutoff = $candidate->copy()->subDay()->startOfDay();

            if ($orderedAt->lessThanOrEqualTo($cutoff)) {
                return $candidate->toDateString();
            }
        }

        return null;
    }

    private function deliveryDates(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        $dates = is_array($value) ? $value : json_decode((string) $value, true);

        if (is_string($dates)) {
            $dates = json_decode($dates, true);
        }

        if (! is_array($dates)) {
            return [];
        }

        return collect($dates)
            ->map(fn (mixed $date) => $this->dateOrNull($date))
            ->filter()
            ->values()
            ->all();
    }
}
