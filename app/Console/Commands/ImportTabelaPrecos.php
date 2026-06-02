<?php

namespace App\Console\Commands;

use App\Models\CompraPrecoMapping;
use App\Models\TabelaPreco;
use App\Models\TabelaPrecoItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportTabelaPrecos extends Command
{
    protected $signature = 'precos:import {path=storage/app/precos-cabazes.json} {--replace : Apaga os itens de cada tabela antes de importar}';

    protected $description = 'Importa tabelas de precos e associacoes das compras a partir de JSON.';

    public function handle(): int
    {
        $path = $this->argument('path');
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
            ? $path
            : base_path($path);

        if (! File::exists($path)) {
            $this->error('Ficheiro nao encontrado: '.$path);

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($path), true);

        if (! is_array($payload) || ! isset($payload['tabelas']) || ! is_array($payload['tabelas'])) {
            $this->error('JSON invalido. Esperava a chave "tabelas".');

            return self::FAILURE;
        }

        $tabelasImportadas = 0;
        $itensImportados = 0;

        foreach ($payload['tabelas'] as $tabelaData) {
            $tabela = TabelaPreco::updateOrCreate(
                [
                    'fornecedor' => $tabelaData['fornecedor'],
                    'valida_de' => $tabelaData['valida_de'],
                ],
                [
                    'descricao' => $tabelaData['descricao'] ?? null,
                    'valida_ate' => $tabelaData['valida_ate'] ?? null,
                    'ativa' => (bool) ($tabelaData['ativa'] ?? true),
                ],
            );

            if ($this->option('replace')) {
                $tabela->itens()->delete();
            }

            foreach ($tabelaData['itens'] ?? [] as $itemData) {
                $tabela->itens()->updateOrCreate(
                    [
                        'produto' => $itemData['produto'],
                        'categoria' => $itemData['categoria'],
                        'origem' => $itemData['origem'] ?? null,
                        'calibre' => $itemData['calibre'] ?? null,
                    ],
                    [
                        'preco_kg' => $itemData['preco_kg'],
                        'preco_kg_iva' => $itemData['preco_kg_iva'],
                        'unidade' => $itemData['unidade'] ?? 'kg',
                        'notas' => $itemData['notas'] ?? null,
                        'ordem' => $itemData['ordem'] ?? 0,
                    ],
                );
                $itensImportados++;
            }

            $tabelasImportadas++;
        }

        foreach ($payload['mapeamentos_compras'] ?? [] as $mappingData) {
            $itemData = $mappingData['item'] ?? null;

            if (! is_array($itemData)) {
                continue;
            }

            $item = TabelaPrecoItem::query()
                ->where('produto', $itemData['produto'])
                ->where('categoria', $itemData['categoria'])
                ->whereHas('tabelaPreco', fn ($query) => $query
                    ->where('fornecedor', $itemData['fornecedor'])
                    ->whereDate('valida_de', $itemData['valida_de']))
                ->first();

            if ($item === null) {
                continue;
            }

            CompraPrecoMapping::updateOrCreate(
                ['produto' => $mappingData['produto']],
                ['tabela_preco_item_id' => $item->id],
            );
        }

        $this->info("Importadas {$tabelasImportadas} tabelas e {$itensImportados} produtos.");

        return self::SUCCESS;
    }
}
