<?php

namespace App\Console\Commands;

use App\Models\WooOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class RecalcularCabazTipo extends Command
{
    protected $signature = 'woo:recalcular-cabaz-tipo';

    protected $description = 'Recalcula o tipo de cabaz das subscricoes B2C existentes.';

    public function handle(): int
    {
        $atualizadas = 0;

        WooOrder::query()
            ->where('source_type', 'subscription')
            ->orderBy('id')
            ->each(function (WooOrder $order) use (&$atualizadas): void {
                $tipo = WooOrder::detectarCabazTipo(Arr::get($order->raw_payload ?? [], 'line_items', $order->line_items ?? []));

                if ($order->cabaz_tipo !== $tipo) {
                    $order->update(['cabaz_tipo' => $tipo]);
                    $atualizadas++;
                }
            });

        $this->info("{$atualizadas} subscricoes atualizadas.");

        return self::SUCCESS;
    }
}
