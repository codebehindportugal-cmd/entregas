<?php

namespace App\Console\Commands;

use App\Services\EntregasDoDia;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Poe as entregas dos proximos dias na zona do seu codigo postal (encomendas
 * B2C novas, renovacoes, empresas novas), para ninguem as ter de atribuir.
 */
class AtribuirZonasCommand extends Command
{
    protected $signature = 'entregas:atribuir-zonas {--dias=14 : Quantos dias para a frente, a contar de hoje}';

    protected $description = 'Atribui automaticamente a zona (pelo codigo postal) as entregas dos proximos dias';

    public function handle(EntregasDoDia $entregasDoDia): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $total = 0;

        for ($i = 0; $i < $dias; $i++) {
            $total += $entregasDoDia->garantirZonas(now()->startOfDay()->addDays($i));
        }

        $this->info($total === 1 ? '1 entrega ficou com zona.' : "{$total} entregas ficaram com zona.");

        return self::SUCCESS;
    }
}
