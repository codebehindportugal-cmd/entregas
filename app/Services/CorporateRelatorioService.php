<?php

namespace App\Services;

use App\Models\Corporate;
use App\Models\CorporateHistorico;
use App\Models\RegistoEntrega;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CorporateRelatorioService
{
    private const DIAS_SEMANA = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    public function buildLinhas(Corporate $corporate, Carbon $inicio, Carbon $fim): Collection
    {
        $registos = RegistoEntrega::query()
            ->where('corporate_id', $corporate->id)
            ->whereBetween('data_entrega', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn (RegistoEntrega $r): string => $r->data_entrega->toDateString());

        $historicosNaoEntregamos = $corporate->historicos()
            ->where('tipo', 'nao_entregamos')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn (CorporateHistorico $h): string => $h->data->toDateString());

        $historicosEntregaParcial = $corporate->historicos()
            ->where('tipo', 'entrega_parcial')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn (CorporateHistorico $h): string => $h->data->toDateString());

        $linhas = collect();
        $data = $inicio->copy();

        while ($data->lessThanOrEqualTo($fim)) {
            $diaSemana = self::DIAS_SEMANA[$data->dayOfWeek] ?? null;
            $configuracaoDia = $this->configuracaoParaData($corporate, $data);
            $diasEntregaDia = $configuracaoDia['dias_entrega'] ?? [];

            if ($diaSemana !== null && in_array($diaSemana, $diasEntregaDia, true) && $this->temEntregaNaData($configuracaoDia, $data)) {
                $dataKey = $data->toDateString();
                $registo = $registos->get($dataKey);
                $historicoNaoEntregamos = $historicosNaoEntregamos->get($dataKey);
                $historicoEntregaParcial = $historicosEntregaParcial->get($dataKey);
                $estado = $historicoNaoEntregamos !== null
                    ? 'nao_entregamos'
                    : ($historicoEntregaParcial !== null ? 'entrega_parcial'
                    : ($registo?->status ?? 'sem_registo'));
                $pecasEntregues = $estado === 'entrega_parcial'
                    ? (int) ($historicoEntregaParcial?->pecas_entregues ?? 0)
                    : null;

                $linhas->push([
                    'data' => $data->copy(),
                    'dia' => $diaSemana,
                    'estado' => $estado,
                    'hora_entrega' => $registo?->hora_entrega,
                    'pecas_entregues' => $pecasEntregues,
                    'nota' => collect([$registo?->nota, $historicoNaoEntregamos?->texto, $historicoEntregaParcial?->texto])->filter()->implode("\n"),
                ]);
            }

            $data->addDay();
        }

        return $linhas;
    }

    public function configuracaoParaData(Corporate $corporate, Carbon $data): array
    {
        $corporate->loadMissing('configSnapshots');

        $snapshot = $corporate->configSnapshots
            ->filter(fn ($s): bool => $s->effective_from->lessThanOrEqualTo($data))
            ->sortByDesc('effective_from')
            ->first();

        return $snapshot?->dados ?? $corporate->snapshotDados();
    }

    public function temEntregaNaData(array $configuracao, Carbon $data): bool
    {
        if (($configuracao['periodicidade_entrega'] ?? 'semanal') !== 'quinzenal' || blank($configuracao['quinzenal_referencia'] ?? null)) {
            return true;
        }

        $semanaReferencia = Carbon::parse($configuracao['quinzenal_referencia'])->startOfWeek();
        $semanaDaData = $data->copy()->startOfWeek();

        return ((int) $semanaReferencia->diffInWeeks($semanaDaData)) % 2 === 0;
    }
}
