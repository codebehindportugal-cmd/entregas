<?php

namespace App\Console\Commands;

use App\Services\WooCommerceService;
use Illuminate\Console\Command;
use Throwable;

class SyncWooOrders extends Command
{
    protected $signature = 'orders:sync';

    protected $description = 'Sincroniza encomendas B2C a partir do WooCommerce.';

    public function handle(WooCommerceService $service): int
    {
        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("WooCommerce sincronizado: {$result['fetched']} lidas ({$result['orders']} encomendas, {$result['subscriptions']} subscricoes), {$result['created']} criadas, {$result['updated']} atualizadas.");

        return self::SUCCESS;
    }
}
