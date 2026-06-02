<?php

namespace App\Console\Commands;

use App\Models\CompraPrecoMapping;
use App\Models\TabelaPreco;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportTabelaPrecos extends Command
{
    protected $signature = 'precos:export {path=storage/app/precos-cabazes.json}';

    protected $description = 'Exporta tabelas de precos e associacoes das compras para JSON.';

    public function handle(): int
    {
        $path = $this->argument('path');
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($path));

        $tabelas = TabelaPreco::query()
            ->with(['itens' => fn ($query) => $query->orderBy('ordem')->orderBy('produto')])
            ->orderBy('fornecedor')
            ->orderByDesc('valida_de')
            ->get()
            ->map(fn (TabelaPreco $tabela): array => [
                'fornecedor' => $tabela->fornecedor,
                'descricao' => $tabela->descricao,
                'valida_de' => $tabela->valida_de?->toDateString(),
                'valida_ate' => $tabela->valida_ate?->toDateString(),
                'ativa' => $tabela->ativa,
                'itens' => $tabela->itens->map(fn ($item): array => [
                    'categoria' => $item->categoria,
                    'produto' => $item->produto,
                    'origem' => $item->origem,
                    'calibre' => $item->calibre,
                    'epoca' => $item->epoca,
                    'em_epoca' => (bool) $item->em_epoca,
                    'disponivel_compra' => (bool) $item->disponivel_compra,
                    'woo_product_id' => $item->woo_product_id,
                    'woo_variation_id' => $item->woo_variation_id,
                    'preco_kg' => (float) $item->preco_kg,
                    'preco_kg_iva' => (float) $item->preco_kg_iva,
                    'unidade' => $item->unidade,
                    'notas' => $item->notas,
                    'ordem' => $item->ordem,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $mapeamentos = CompraPrecoMapping::query()
            ->with('tabelaPrecoItem.tabelaPreco')
            ->get()
            ->map(fn (CompraPrecoMapping $mapping): array => [
                'produto' => $mapping->produto,
                'item' => $mapping->tabelaPrecoItem ? [
                    'fornecedor' => $mapping->tabelaPrecoItem->tabelaPreco?->fornecedor,
                    'valida_de' => $mapping->tabelaPrecoItem->tabelaPreco?->valida_de?->toDateString(),
                    'produto' => $mapping->tabelaPrecoItem->produto,
                    'categoria' => $mapping->tabelaPrecoItem->categoria,
                    'woo_product_id' => $mapping->tabelaPrecoItem->woo_product_id,
                    'woo_variation_id' => $mapping->tabelaPrecoItem->woo_variation_id,
                ] : null,
            ])
            ->values()
            ->all();

        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'tabelas' => $tabelas,
            'mapeamentos_compras' => $mapeamentos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Exportado: '.$path);
        $this->info(count($tabelas).' tabelas de precos exportadas.');

        return self::SUCCESS;
    }
}
