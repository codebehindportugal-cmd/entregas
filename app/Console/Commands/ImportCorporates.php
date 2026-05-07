<?php

namespace App\Console\Commands;

use App\Models\Corporate;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ImportCorporates extends Command
{
    protected $signature = 'corporates:import {path=storage/app/exports/corporates.json : Caminho do ficheiro JSON} {--dry-run : Mostra o que faria sem gravar}';

    protected $description = 'Importa empresas de JSON, criando ou atualizando por empresa e sucursal.';

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    private const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    public function handle(): int
    {
        try {
            $path = $this->absolutePath((string) $this->argument('path'));

            if (! File::exists($path)) {
                throw new RuntimeException("Ficheiro nao encontrado: {$path}");
            }

            $payload = json_decode(File::get($path), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON invalido: '.json_last_error_msg());
            }

            $rows = $payload['corporates'] ?? null;

            if (! is_array($rows)) {
                throw new RuntimeException('Formato invalido: falta a chave corporates.');
            }

            $created = 0;
            $updated = 0;
            $dryRun = (bool) $this->option('dry-run');

            $import = function () use ($rows, $dryRun, &$created, &$updated): void {
                foreach ($rows as $index => $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException('Linha '.($index + 1).': esperado objeto/array de empresa.');
                    }

                    $data = $this->normalizeRow($row, $index + 1);
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
            };

            $dryRun ? $import() : DB::transaction($import);

            $prefix = $dryRun ? 'Dry-run: ' : '';
            $this->info("{$prefix}{$created} criadas, {$updated} atualizadas.");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function normalizeRow(array $row, int $line): array
    {
        $diasEntrega = is_array($row['dias_entrega'] ?? null) ? $row['dias_entrega'] : [];

        $row = [
            ...$row,
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'sucursal' => $this->nullableString($row['sucursal'] ?? null),
            'dias_entrega' => array_values(array_filter($diasEntrega, fn (mixed $dia): bool => filled($dia))),
        ];

        $validator = Validator::make($row, [
            'empresa' => ['required', 'string', 'max:255'],
            'sucursal' => ['nullable', 'string', 'max:255'],
            'morada_entrega' => ['nullable', 'string', 'max:500'],
            'dias_entrega' => ['array'],
            'dias_entrega.*' => ['in:Segunda,Terca,Quarta,Quinta,Sexta'],
            'periodicidade_entrega' => ['nullable', 'in:semanal,quinzenal'],
            'quinzenal_referencia' => ['nullable', 'date'],
            'preco_venda_peca' => ['nullable', 'numeric', 'min:0'],
            'fatura_email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Linha '.$line.': '.$validator->errors()->first());
        }

        $periodicidade = in_array($row['periodicidade_entrega'] ?? null, ['semanal', 'quinzenal'], true)
            ? $row['periodicidade_entrega']
            : 'semanal';
        $frutas = $this->normalizeFruits($row['frutas'] ?? []);
        $frutasPorDia = collect($row['frutas_por_dia'] ?? [])
            ->filter(fn (mixed $values) => is_array($values))
            ->map(fn (array $values) => $this->normalizeFruits($values))
            ->filter(fn (array $values) => array_sum($values) > 0)
            ->all();

        return [
            'empresa' => $row['empresa'],
            'sucursal' => $row['sucursal'],
            'morada_entrega' => $this->nullableString($row['morada_entrega'] ?? null),
            'dias_entrega' => $row['dias_entrega'],
            'periodicidade_entrega' => $periodicidade,
            'quinzenal_referencia' => $periodicidade === 'quinzenal' ? ($row['quinzenal_referencia'] ?? null) : null,
            'horario_entrega' => $this->nullableString($row['horario_entrega'] ?? null),
            'responsavel_nome' => $this->nullableString($row['responsavel_nome'] ?? null),
            'responsavel_telefone' => $this->nullableString($row['responsavel_telefone'] ?? null),
            'fatura_nome' => $this->nullableString($row['fatura_nome'] ?? null),
            'fatura_nif' => $this->nullableString($row['fatura_nif'] ?? null),
            'fatura_email' => $this->nullableString($row['fatura_email'] ?? null),
            'fatura_morada' => $this->nullableString($row['fatura_morada'] ?? null),
            'numero_caixas' => max(0, (int) ($row['numero_caixas'] ?? 0)),
            'preco_venda_peca' => $this->nullableDecimal($row['preco_venda_peca'] ?? null),
            'peso_total' => max(0, (float) ($row['peso_total'] ?? 0)),
            'frutas' => $frutas,
            'frutas_por_dia' => $frutasPorDia,
            'notas' => $this->nullableString($row['notas'] ?? null),
            'ativo' => $this->boolValue($row['ativo'] ?? true),
        ];
    }

    private function normalizeFruits(array $values): array
    {
        return collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruit) => [$fruit => in_array($fruit, self::PRODUTOS_KG, true)
                ? round(max(0, (float) ($values[$fruit] ?? 0)), 2)
                : max(0, (int) ($values[$fruit] ?? 0))])
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return max(0, (float) $value);
    }

    private function boolValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }
}
