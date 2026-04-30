<?php

namespace Database\Seeders;

use App\Models\Corporate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use RuntimeException;

class CorporateSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('imports/corporates.csv');

        if (! file_exists($path)) {
            throw new RuntimeException("Ficheiro de importacao nao encontrado: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Nao foi possivel abrir o ficheiro: {$path}");
        }

        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            if (blank($data['empresa'] ?? null)) {
                continue;
            }

            $notas = collect([
                filled($data['zona_entrega'] ?? null) ? 'Zona de entrega: '.$data['zona_entrega'] : null,
                filled($data['localidade'] ?? null) ? 'Localidade: '.$data['localidade'] : null,
                filled($data['notas'] ?? null) ? $data['notas'] : null,
            ])->filter()->implode(PHP_EOL);

            // O ficheiro original usa "quantidade semanal"; guardamos esse total de pecas por semana em peso_total.
            Corporate::updateOrCreate(
                ['empresa' => $data['empresa']],
                [
                    'sucursal' => Arr::get($data, 'sucursal') ?: null,
                    'dias_entrega' => $this->diasEntrega($data['dias_entrega'] ?? ''),
                    'periodicidade_entrega' => 'semanal',
                    'quinzenal_referencia' => null,
                    'horario_entrega' => Arr::get($data, 'horario_entrega') ?: null,
                    'responsavel_nome' => null,
                    'responsavel_telefone' => null,
                    'fatura_nome' => $data['empresa'],
                    'fatura_nif' => null,
                    'fatura_email' => null,
                    'fatura_morada' => Arr::get($data, 'fatura_morada') ?: null,
                    'numero_caixas' => max(0, (int) ($data['numero_caixas'] ?? 0)),
                    'peso_total' => max(0, (float) ($data['peso_total'] ?? 0)),
                    'frutas' => [
                        'banana' => max(0, (int) ($data['banana'] ?? 0)),
                        'maca' => max(0, (int) ($data['maca'] ?? 0)),
                        'pera' => max(0, (int) ($data['pera'] ?? 0)),
                        'laranja' => max(0, (int) ($data['laranja'] ?? 0)),
                        'kiwi' => max(0, (int) ($data['kiwi'] ?? 0)),
                        'uvas' => max(0, (float) ($data['uvas'] ?? 0)),
                        'fruta_epoca' => max(0, (int) ($data['fruta_epoca'] ?? 0)),
                    ],
                    'notas' => $notas ?: null,
                    'ativo' => true,
                ]
            );
        }

        fclose($handle);
    }

    /**
     * Converte os dias exportados do Excel para o formato JSON usado na aplicacao.
     */
    private function diasEntrega(string $dias): array
    {
        return collect(explode('|', $dias))
            ->map(fn (string $dia) => trim($dia))
            ->filter()
            ->values()
            ->all();
    }
}
