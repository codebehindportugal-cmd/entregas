<?php

namespace App\Console\Commands;

use App\Models\Corporate;
use Illuminate\Console\Command;

class NormalizeCorporateFruits extends Command
{
    protected $signature = 'corporates:normalize-fruits {--dry-run : Mostra o que mudaria sem gravar}';

    protected $description = 'Normaliza frutas das empresas e copia valores base para os dias de entrega vazios.';

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    private const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $checked = 0;

        Corporate::query()->orderBy('id')->each(function (Corporate $corporate) use (&$updated, &$checked, $dryRun): void {
            $checked++;
            $frutas = $this->normalizarFrutas($corporate->frutas ?? []);
            $frutasPorDia = $corporate->frutas_por_dia ?? [];
            $changed = $frutas !== ($corporate->frutas ?? []);

            foreach ($corporate->dias_entrega ?? [] as $dia) {
                $valoresDia = $this->normalizarFrutas($frutasPorDia[$dia] ?? []);

                if (array_sum($valoresDia) <= 0) {
                    $valoresDia = $frutas;
                }

                if (($frutasPorDia[$dia] ?? null) !== $valoresDia) {
                    $frutasPorDia[$dia] = $valoresDia;
                    $changed = true;
                }
            }

            $frutasPorDia = collect($frutasPorDia)
                ->map(fn (array $valores) => $this->normalizarFrutas($valores))
                ->filter(fn (array $valores) => array_sum($valores) > 0)
                ->all();

            if (! $changed) {
                return;
            }

            $updated++;
            $this->line("#{$corporate->id} {$corporate->empresa}");

            if (! $dryRun) {
                $corporate->forceFill([
                    'frutas' => $frutas,
                    'frutas_por_dia' => $frutasPorDia,
                ])->save();
            }
        });

        $this->info(($dryRun ? 'Dry-run: ' : '')."{$updated} de {$checked} empresas normalizadas.");

        return self::SUCCESS;
    }

    private function normalizarFrutas(array $valores): array
    {
        return collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruta) => [$fruta => in_array($fruta, self::PRODUTOS_KG, true)
                ? round(max(0, (float) ($valores[$fruta] ?? 0)), 2)
                : max(0, (int) ($valores[$fruta] ?? 0))])
            ->all();
    }
}
