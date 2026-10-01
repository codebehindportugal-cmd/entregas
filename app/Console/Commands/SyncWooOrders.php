<?php

namespace App\Console\Commands;

use App\Services\WooCommerceService;
use App\Support\Ntfy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncWooOrders extends Command
{
    protected $signature = 'orders:sync';

    protected $description = 'Sincroniza encomendas B2C a partir do WooCommerce.';

    private const CHAVE_FALHA = 'orders_sync_a_falhar_desde';

    public function handle(WooCommerceService $service): int
    {
        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            // 29/09/2026: uma falha aqui so ficava na consola do cron. Avisa-se
            // na primeira falha seguida, nao de 15 em 15 minutos.
            if (! Cache::get(self::CHAVE_FALHA)) {
                Cache::forever(self::CHAVE_FALHA, now()->toIso8601String());
                Ntfy::falhou(
                    'sincronizacao',
                    'Horta: sincronizacao com o Woo a falhar',
                    mb_substr($exception->getMessage(), 0, 300),
                    rtrim((string) config('app.url'), '/'),
                );
            }

            return self::FAILURE;
        }

        if ($desde = Cache::pull(self::CHAVE_FALHA)) {
            Ntfy::recuperou(
                'sincronizacao',
                'Horta: sincronizacao com o Woo recuperou',
                'Voltou a correr bem. Estava a falhar desde ' . \Illuminate\Support\Carbon::parse($desde)->format('d/m H:i') . '.',
            );
        }

        $this->info("WooCommerce sincronizado: {$result['fetched']} lidas ({$result['orders']} encomendas, {$result['subscriptions']} subscricoes), {$result['created']} criadas, {$result['updated']} atualizadas.");

        return self::SUCCESS;
    }
}
