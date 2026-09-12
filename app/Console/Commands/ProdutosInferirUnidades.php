<?php

namespace App\Console\Commands;

use App\Models\WooProduct;
use App\Services\InferidorUnidadesProduto;
use Illuminate\Console\Command;

/**
 * Preenche o formato de venda dos produtos que ainda o nao tem.
 *
 * Serve para nao preencher duzias de produtos a mao da primeira vez. O que sai
 * daqui e um palpite pelo nome: correr com --dry-run, olhar para a coluna da
 * confianca e confirmar no ecra dos produtos o que estiver a baixo.
 */
class ProdutosInferirUnidades extends Command
{
    protected $signature = 'produtos:inferir-unidades {--dry-run : So mostra o que faria} {--force : Tambem mexe nos ja confirmados}';

    protected $description = 'Infere pelo nome se cada produto se vende a unidade ou ao peso';

    public function handle(InferidorUnidadesProduto $inferidor): int
    {
        $produtos = WooProduct::query()->orderBy('name')->get();

        if ($produtos->isEmpty()) {
            $this->warn('Ainda nao ha produtos sincronizados do site.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $linhas = [];
        $alterados = 0;

        foreach ($produtos as $produto) {
            if ($produto->unidades_confirmadas && ! $force) {
                continue;
            }

            $inferido = $inferidor->infer($produto);
            $confianca = $inferido['confianca'];

            $linhas[] = [
                $produto->name,
                $inferido['unidade_venda'],
                $inferido['unidade_venda'] === 'peso'
                    ? rtrim(rtrim(number_format($inferido['formato_qtd'], 3, ',', ''), '0'), ',').' kg'
                    : '1 un',
                $inferido['peso_medio_kg'] !== null ? $inferido['peso_medio_kg'].' kg' : '-',
                $confianca,
            ];

            if (! $dryRun) {
                $inferidor->aplicar($produto, $force);
                $produto->save();
            }

            $alterados++;
        }

        if ($linhas === []) {
            $this->info('Nao ha nada por inferir: todos os produtos ja estao confirmados.');

            return self::SUCCESS;
        }

        $this->table(['Produto', 'Vende-se', 'Formato', 'Peso medio', 'Confianca'], $linhas);

        $baixas = collect($linhas)->where(4, 'baixa')->count();

        if ($baixas > 0) {
            $this->warn("{$baixas} produtos ficaram com confianca baixa — confirma-os no ecra dos produtos.");
        }

        $this->info($dryRun
            ? "{$alterados} produtos seriam alterados (dry-run, nada foi gravado)."
            : "{$alterados} produtos atualizados. Ficam por confirmar ate alguem os validar no backoffice.");

        return self::SUCCESS;
    }
}
