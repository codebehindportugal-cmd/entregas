<?php

namespace App\Services;

use App\Models\Corporate;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ComprasService
{
    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
    ];

    public const FRUTAS = [
        'banana' => 'Bananas',
        'maca' => 'Macas',
        'pera' => 'Peras',
        'laranja' => 'Laranjas',
        'kiwi' => 'Kiwis',
        'uvas' => 'Uvas',
        'fruta_epoca' => 'Fruta epoca',
    ];

    public const PESOS_PADRAO = [
        'banana' => 0.18,
        'maca' => 0.16,
        'pera' => 0.18,
        'laranja' => 0.20,
        'kiwi' => 0.08,
        'uvas' => 0.20,
        'fruta_epoca' => 0.18,
    ];

    public function calcular(Carbon $inicio, Carbon $fim, array $pesos): array
    {
        $pesos = $this->normalizarPesos($pesos);
        $dias = collect();
        $totaisPecas = $this->frutasVazias();
        $totaisKg = $this->frutasVazias();
        $totalCaixas = 0;
        $totalClientes = 0;

        foreach (CarbonPeriod::create($inicio, $fim) as $data) {
            $diaSemana = self::DIAS[$data->dayOfWeek] ?? null;

            if ($diaSemana === null) {
                continue;
            }

            $corporates = $this->corporatesParaDia($data, $diaSemana);
            $pecas = $this->frutasVazias();

            foreach ($corporates as $corporate) {
                foreach ($corporate->frutasParaDia($diaSemana) as $fruta => $quantidade) {
                    $pecas[$fruta] = ($pecas[$fruta] ?? 0) + ($fruta === 'uvas' ? (float) $quantidade : (int) $quantidade);
                }
            }

            $kg = collect($pecas)
                ->mapWithKeys(fn (int|float $quantidade, string $fruta) => [$fruta => $fruta === 'uvas' ? round($quantidade, 2) : round($quantidade * $pesos[$fruta], 2)])
                ->all();

            foreach (array_keys(self::FRUTAS) as $fruta) {
                $totaisPecas[$fruta] += $pecas[$fruta];
                $totaisKg[$fruta] += $kg[$fruta];
            }

            $caixas = $corporates->sum('numero_caixas');
            $totalCaixas += $caixas;
            $totalClientes += $corporates->count();

            $dias->push([
                'data' => $data->copy(),
                'dia' => $diaSemana,
                'clientes' => $corporates->count(),
                'caixas' => $caixas,
                'pecas' => $pecas,
                'kg' => $kg,
                'total_pecas' => array_sum(collect($pecas)->except('uvas')->all()),
                'total_kg' => round(array_sum($kg), 2),
            ]);
        }

        return [
            'dias' => $dias,
            'pesos' => $pesos,
            'totais_pecas' => $totaisPecas,
            'totais_kg' => collect($totaisKg)->map(fn (float $valor) => round($valor, 2))->all(),
            'total_pecas' => array_sum(collect($totaisPecas)->except('uvas')->all()),
            'total_kg' => round(array_sum($totaisKg), 2),
            'total_caixas' => $totalCaixas,
            'total_clientes' => $totalClientes,
        ];
    }

    private function corporatesParaDia(Carbon $data, string $diaSemana): Collection
    {
        return Corporate::where('ativo', true)
            ->whereJsonContains('dias_entrega', $diaSemana)
            ->orderBy('empresa')
            ->get()
            ->filter(fn (Corporate $corporate) => $corporate->temEntregaNaData($data))
            ->values();
    }

    private function normalizarPesos(array $pesos): array
    {
        return collect(self::PESOS_PADRAO)
            ->mapWithKeys(fn (float $peso, string $fruta) => [$fruta => max(0, (float) ($pesos[$fruta] ?? $peso))])
            ->all();
    }

    private function frutasVazias(): array
    {
        return collect(array_keys(self::FRUTAS))
            ->mapWithKeys(fn (string $fruta) => [$fruta => 0])
            ->all();
    }
}
