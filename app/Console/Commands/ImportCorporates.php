<?php

namespace App\Console\Commands;

use App\Models\Corporate;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportCorporates extends Command
{
    protected $signature = 'corporates:import {path=storage/app/exports/corporates.json : Caminho do ficheiro JSON} {--dry-run : Mostra o que faria sem gravar}';

    protected $description = 'Importa empresas de JSON, criando ou atualizando por empresa e sucursal.';

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca'];

    public function handle(): int
    {
        $path = $this->absolutePath((string) $this->argument('path'));

        if (! File::exists($path)) {
            throw new RuntimeException("Ficheiro nao encontrado: {$path}");
        }

        $payload = json_decode(File::get($path), true);
        $rows = $payload['corporates'] ?? null;

        if (! is_array($rows)) {
            throw new RuntimeException('Formato invalido: falta a chave corporates.');
        }

        $created = 0;
        $updated = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($rows as $row) {
            $data = $this->normalizeRow($row);
            $keys = [
                'empresa' => $data['empresa'],
                'sucursal' => $data['sucursal'],
            ];

            $exists = Corporate::where($keys)->exists();
            $exists ? $updated++ : $created++;

            $this->line(($exists ? 'Atualizar' : 'Criar').' '.$data['empresa'].($data['sucursal'] ? ' - '.$data['sucursal'] : ''));

            if (! $dryRun) {
                Corporate::updateOrCreate($keys, Arr::except($data, ['empresa', 'sucursal']));
            }
        }

        $prefix = $dryRun ? 'Dry-run: ' : '';
        $this->info("{$prefix}{$created} criadas, {$updated} atualizadas.");

        return self::SUCCESS;
    }

    private function normalizeRow(array $row): array
    {
        $periodicidade = in_array($row['periodicidade_entrega'] ?? null, ['semanal', 'quinzenal'], true)
            ? $row['periodicidade_entrega']
            : 'semanal';
        $frutas = $this->normalizeFruits($row['frutas'] ?? []);
        $frutasPorDia = collect($row['frutas_por_dia'] ?? [])
            ->map(fn (array $values) => $this->normalizeFruits($values))
            ->filter(fn (array $values) => array_sum($values) > 0)
            ->all();

        return [
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'sucursal' => filled($row['sucursal'] ?? null) ? trim((string) $row['sucursal']) : null,
            'dias_entrega' => array_values($row['dias_entrega'] ?? []),
            'periodicidade_entrega' => $periodicidade,
            'quinzenal_referencia' => $periodicidade === 'quinzenal' ? ($row['quinzenal_referencia'] ?? null) : null,
            'horario_entrega' => $row['horario_entrega'] ?? null,
            'responsavel_nome' => $row['responsavel_nome'] ?? null,
            'responsavel_telefone' => $row['responsavel_telefone'] ?? null,
            'fatura_nome' => $row['fatura_nome'] ?? null,
            'fatura_nif' => $row['fatura_nif'] ?? null,
            'fatura_email' => $row['fatura_email'] ?? null,
            'fatura_morada' => $row['fatura_morada'] ?? null,
            'numero_caixas' => max(0, (int) ($row['numero_caixas'] ?? 0)),
            'peso_total' => max(0, (float) ($row['peso_total'] ?? 0)),
            'frutas' => $frutas,
            'frutas_por_dia' => $frutasPorDia,
            'notas' => $row['notas'] ?? null,
            'ativo' => (bool) ($row['ativo'] ?? true),
        ];
    }

    private function normalizeFruits(array $values): array
    {
        return collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruit) => [$fruit => $fruit === 'uvas'
                ? round(max(0, (float) ($values[$fruit] ?? 0)), 2)
                : max(0, (int) ($values[$fruit] ?? 0))])
            ->all();
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }
}
