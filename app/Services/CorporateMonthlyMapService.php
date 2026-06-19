<?php

namespace App\Services;

use App\Models\Corporate;
use App\Models\CorporateHistorico;
use App\Models\RegistoEntrega;
use Illuminate\Support\Carbon;

class CorporateMonthlyMapService
{
    private const DIAS_SEMANA = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    private const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    private const LABELS_KG = [
        'uvas' => 'Uvas',
        'frutos_secos' => 'Frutos secos',
        'mirtilos' => 'Mirtilos',
        'framboesas' => 'Framboesas',
        'amoras' => 'Amoras',
        'morangos' => 'Morangos',
    ];

    private const LABELS_PASTELARIA = [
        'pao_mistura' => 'Pao mistura',
        'pao_forma' => 'Pao forma',
        'croissant' => 'Croissants',
        'bolo' => 'Bolos',
    ];

    public function build(Corporate $corporate, ?string $mes = null): array
    {
        $corporate->loadMissing('configSnapshots');

        $mes ??= now()->format('Y-m');
        $inicio = Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        $registos = RegistoEntrega::query()
            ->where('corporate_id', $corporate->id)
            ->whereBetween('data_entrega', [$inicio->toDateString(), $fim->toDateString()])
            ->whereIn('status', ['entregue', 'falhou'])
            ->get()
            ->keyBy(fn (RegistoEntrega $registo): string => $registo->data_entrega->toDateString());

        $historicosMes = $corporate->historicos()
            ->with('user')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data')
            ->orderBy('created_at')
            ->get();

        $historicosNaoEntregamos = $historicosMes
            ->where('tipo', 'nao_entregamos')
            ->keyBy(fn (CorporateHistorico $historico): string => $historico->data->toDateString());
        $historicosEntregaParcial = $historicosMes
            ->where('tipo', 'entrega_parcial')
            ->keyBy(fn (CorporateHistorico $historico): string => $historico->data->toDateString());
        $historicosEntregaExtra = $historicosMes
            ->where('tipo', 'entrega_extra')
            ->groupBy(fn (CorporateHistorico $historico): string => $historico->data->toDateString());

        $linhas = collect();
        $data = $inicio->copy();

        while ($data->lessThanOrEqualTo($fim)) {
            $diaSemana = self::DIAS_SEMANA[$data->dayOfWeek] ?? null;
            $dataKey = $data->toDateString();
            $historicosExtraDoDia = $historicosEntregaExtra->get($dataKey, collect());
            $configuracaoDia = $this->configuracaoCorporateParaData($corporate, $data);
            $diasEntregaDia = $configuracaoDia['dias_entrega'] ?? [];
            $temEntregaRegular = $diaSemana !== null
                && in_array($diaSemana, $diasEntregaDia, true)
                && $this->configuracaoTemEntregaNaData($configuracaoDia, $data);

            if ($temEntregaRegular || $historicosExtraDoDia->isNotEmpty()) {
                $registo = $registos->get($dataKey);
                $historicoNaoEntregamos = $historicosNaoEntregamos->get($dataKey);
                $historicoEntregaParcial = $historicosEntregaParcial->get($dataKey);
                $pecasRegular = $temEntregaRegular ? (int) ($configuracaoDia['pecas_por_dia'][$diaSemana] ?? 0) : 0;
                $produtosRegular = $temEntregaRegular ? $this->produtosMapaParaData($corporate, $configuracaoDia, $diaSemana, $data) : [
                    'kg' => [],
                    'pastelaria' => [],
                ];
                $pecasExtra = $historicosExtraDoDia->sum(fn (CorporateHistorico $historico): int => (int) ($historico->pecas_entregues ?? 0));
                $notasExtra = $historicosExtraDoDia->pluck('texto')->filter()->values();
                $status = $historicoNaoEntregamos !== null
                    ? 'nao_entregamos'
                    : ($historicoEntregaParcial !== null ? 'entrega_parcial'
                    : ($registo?->status ?? 'sem_registo'));
                $pecasEntregues = match ($status) {
                    'nao_entregamos', 'falhou' => 0,
                    'entrega_parcial' => (int) ($historicoEntregaParcial?->pecas_entregues ?? 0),
                    default => $pecasRegular,
                };
                $produtosEntregues = in_array($status, ['nao_entregamos', 'falhou'], true)
                    ? ['kg' => [], 'pastelaria' => []]
                    : $produtosRegular;

                $linhas->push([
                    'data' => $data->copy(),
                    'dia_semana' => $diaSemana,
                    'pecas' => $pecasEntregues + $pecasExtra,
                    'produtos_kg' => $produtosEntregues['kg'],
                    'pastelaria' => $produtosEntregues['pastelaria'],
                    'total_kg' => round(array_sum($produtosEntregues['kg']), 2),
                    'total_pastelaria' => (int) array_sum($produtosEntregues['pastelaria']),
                    'status' => $status,
                    'nota' => collect([$historicoNaoEntregamos?->texto, $historicoEntregaParcial?->texto, $registo?->nota])
                        ->merge($notasExtra)
                        ->filter()
                        ->implode("\n"),
                ]);
            }

            $data->addDay();
        }

        $linhasEntregues = $linhas->reject(fn (array $linha): bool => in_array($linha['status'], ['falhou', 'nao_entregamos'], true));

        return [
            'corporate' => $corporate,
            'mes' => $mes,
            'inicio' => $inicio,
            'mesAnterior' => $inicio->copy()->subMonthNoOverflow()->format('Y-m'),
            'mesSeguinte' => $inicio->copy()->addMonthNoOverflow()->format('Y-m'),
            'linhas' => $linhas,
            'historicos' => $historicosMes,
            'totalDiasEntregues' => $linhasEntregues->count(),
            'totalPecasEntregues' => $linhasEntregues->sum(fn (array $linha): int => (int) $linha['pecas']),
            'totalKgEntregues' => round($linhasEntregues->sum(fn (array $linha): float => (float) ($linha['total_kg'] ?? 0)), 2),
            'totalPastelariaEntregue' => $linhasEntregues->sum(fn (array $linha): int => (int) ($linha['total_pastelaria'] ?? 0)),
            'labelsKg' => self::LABELS_KG,
            'labelsPastelaria' => self::LABELS_PASTELARIA,
        ];
    }

    private function produtosMapaParaData(Corporate $corporate, array $configuracao, string $diaSemana, Carbon $data): array
    {
        $produtosMensais = $configuracao['produtos_mensais'] ?? [];
        $produtosKg = collect($configuracao['produtos_kg_por_dia'][$diaSemana] ?? [])
            ->filter(fn (int|float $quantidade): bool => (float) $quantidade > 0)
            ->reject(fn (int|float $quantidade, string $produto): bool => in_array($produto, $produtosMensais, true)
                && ! $this->produtoMensalEntregaNestaData($corporate, $produto, $data, 'kg'))
            ->map(fn (int|float $quantidade): float => round((float) $quantidade, 2))
            ->all();
        $pastelaria = collect($configuracao['pastelaria_por_dia'][$diaSemana] ?? [])
            ->filter(fn (int|float $quantidade): bool => (int) $quantidade > 0)
            ->reject(fn (int|float $quantidade, string $produto): bool => in_array($produto, $produtosMensais, true)
                && ! $this->produtoMensalEntregaNestaData($corporate, $produto, $data, 'pastelaria'))
            ->map(fn (int|float $quantidade): int => (int) $quantidade)
            ->all();

        return [
            'kg' => $produtosKg,
            'pastelaria' => $pastelaria,
        ];
    }

    private function produtoMensalEntregaNestaData(Corporate $corporate, string $produto, Carbon $data, string $tipo): bool
    {
        $cursor = $data->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($data)) {
            $diaSemana = self::DIAS_SEMANA[$cursor->dayOfWeek] ?? null;

            if ($diaSemana !== null) {
                $configuracao = $this->configuracaoCorporateParaData($corporate, $cursor);
                $diasEntrega = $configuracao['dias_entrega'] ?? [];
                $quantidade = $tipo === 'kg'
                    ? (float) ($configuracao['produtos_kg_por_dia'][$diaSemana][$produto] ?? 0)
                    : (int) ($configuracao['pastelaria_por_dia'][$diaSemana][$produto] ?? 0);

                if (
                    $quantidade > 0
                    && in_array($produto, $configuracao['produtos_mensais'] ?? [], true)
                    && in_array($diaSemana, $diasEntrega, true)
                    && $this->configuracaoTemEntregaNaData($configuracao, $cursor)
                ) {
                    return $cursor->isSameDay($data);
                }
            }

            $cursor->addDay();
        }

        return false;
    }

    private function configuracaoCorporateParaData(Corporate $corporate, Carbon $data): array
    {
        $snapshot = $corporate->configSnapshots
            ->filter(fn ($snapshot): bool => $snapshot->effective_from->lessThanOrEqualTo($data))
            ->sortByDesc('effective_from')
            ->first();

        return $snapshot?->dados ?? $corporate->snapshotDados();
    }

    private function configuracaoTemEntregaNaData(array $configuracao, Carbon $data): bool
    {
        if (($configuracao['periodicidade_entrega'] ?? 'semanal') !== 'quinzenal' || blank($configuracao['quinzenal_referencia'] ?? null)) {
            return true;
        }

        $semanaReferencia = Carbon::parse($configuracao['quinzenal_referencia'])->startOfWeek();
        $semanaDaData = $data->copy()->startOfWeek();

        return ((int) $semanaReferencia->diffInWeeks($semanaDaData)) % 2 === 0;
    }
}
