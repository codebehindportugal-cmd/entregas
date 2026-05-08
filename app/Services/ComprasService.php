<?php

namespace App\Services;

use App\Models\CompraPrecoMapping;
use App\Models\Corporate;
use App\Models\TabelaPreco;
use App\Models\TabelaPrecoItem;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ComprasService
{
    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    public const FRUTAS = [
        'banana' => 'Bananas',
        'maca' => 'Macas',
        'pera' => 'Peras',
        'laranja' => 'Laranjas',
        'kiwi' => 'Kiwis',
        'uvas' => 'Uvas',
        'fruta_epoca' => 'Fruta epoca',
        'frutos_secos' => 'Frutos secos',
        'mirtilos' => 'Mirtilos',
        'framboesas' => 'Framboesas',
        'amoras' => 'Amoras',
        'morangos' => 'Morangos',
    ];

    public const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    public const PESOS_PADRAO = [
        'banana' => 0.18,
        'maca' => 0.16,
        'pera' => 0.18,
        'laranja' => 0.20,
        'kiwi' => 0.08,
        'uvas' => 0.20,
        'fruta_epoca' => 0.18,
        'frutos_secos' => 1,
        'mirtilos' => 1,
        'framboesas' => 1,
        'amoras' => 1,
        'morangos' => 1,
    ];

    public function calcular(Carbon $inicio, Carbon $fim, array $pesos): array
    {
        $pesos = $this->normalizarPesos($pesos);
        $dias = collect();
        $totaisPecas = $this->frutasVazias();
        $totaisKg = $this->frutasVazias();
        $totalCaixas = 0;
        $totalClientes = 0;
        $tabelasPreco = $this->tabelasAtivasParaData($inicio->copy());
        $precoItens = $this->itensDasTabelas($tabelasPreco);
        $mapeamentosPreco = CompraPrecoMapping::query()
            ->with('tabelaPrecoItem.tabelaPreco')
            ->get()
            ->keyBy('produto');
        $precos = $this->precosPorProduto($precoItens, $mapeamentosPreco);

        foreach (CarbonPeriod::create($inicio, $fim) as $data) {
            $diaSemana = self::DIAS[$data->dayOfWeek] ?? null;

            if ($diaSemana === null) {
                continue;
            }

            $corporates = $this->corporatesParaDia($data, $diaSemana);
            $pecas = $this->frutasVazias();

            foreach ($corporates as $corporate) {
                foreach ($corporate->frutasParaDia($diaSemana) as $fruta => $quantidade) {
                    $pecas[$fruta] = ($pecas[$fruta] ?? 0) + (in_array($fruta, self::PRODUTOS_KG, true) ? (float) $quantidade : (int) $quantidade);
                }
            }

            $kg = collect($pecas)
                ->mapWithKeys(fn (int|float $quantidade, string $fruta) => [$fruta => in_array($fruta, self::PRODUTOS_KG, true) ? round($quantidade, 2) : round($quantidade * $pesos[$fruta], 2)])
                ->all();
            $custos = collect($kg)
                ->mapWithKeys(fn (float $quantidade, string $fruta) => [$fruta => round($quantidade * ($precos[$fruta]['preco'] ?? 0), 2)])
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
                'custos' => $custos,
                'total_custo' => round(array_sum($custos), 2),
                'total_pecas' => array_sum(collect($pecas)->except(self::PRODUTOS_KG)->all()),
                'total_kg' => round(array_sum($kg), 2),
            ]);
        }

        $totaisCustos = collect($totaisKg)
            ->mapWithKeys(fn (float $quantidade, string $fruta) => [$fruta => round($quantidade * ($precos[$fruta]['preco'] ?? 0), 2)])
            ->all();

        return [
            'dias' => $dias,
            'pesos' => $pesos,
            'tabela_preco' => $tabelasPreco->first(),
            'tabelas_precos' => $tabelasPreco,
            'preco_itens_disponiveis' => $precoItens,
            'mapeamentos_precos' => $mapeamentosPreco,
            'precos' => $precos,
            'totais_pecas' => $totaisPecas,
            'totais_kg' => collect($totaisKg)->map(fn (float $valor) => round($valor, 2))->all(),
            'totais_custos' => $totaisCustos,
            'total_custo' => round(array_sum($totaisCustos), 2),
            'total_pecas' => array_sum(collect($totaisPecas)->except(self::PRODUTOS_KG)->all()),
            'total_kg' => round(array_sum($totaisKg), 2),
            'total_caixas' => $totalCaixas,
            'total_clientes' => $totalClientes,
        ];
    }

    public function precosParaData(Carbon $data): array
    {
        $tabelasPreco = $this->tabelasAtivasParaData($data);
        $precoItens = $this->itensDasTabelas($tabelasPreco);
        $mapeamentosPreco = CompraPrecoMapping::query()
            ->with('tabelaPrecoItem.tabelaPreco')
            ->get()
            ->keyBy('produto');

        return $this->precosPorProduto($precoItens, $mapeamentosPreco);
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

    private function tabelasAtivasParaData(Carbon $data): Collection
    {
        return TabelaPreco::query()
            ->with(['itens' => fn ($query) => $query->orderBy('ordem')])
            ->where('ativa', true)
            ->whereDate('valida_de', '<=', $data)
            ->where(fn ($query) => $query->whereNull('valida_ate')->orWhereDate('valida_ate', '>=', $data))
            ->orderByDesc('valida_de')
            ->get();
    }

    private function itensDasTabelas(Collection $tabelasPreco): Collection
    {
        return $tabelasPreco
            ->flatMap(fn (TabelaPreco $tabelaPreco) => $tabelaPreco->itens->map(function (TabelaPrecoItem $item) use ($tabelaPreco): TabelaPrecoItem {
                $item->setRelation('tabelaPreco', $tabelaPreco);

                return $item;
            }))
            ->values();
    }

    private function precosPorProduto(Collection $itens, Collection $mapeamentosPreco): array
    {
        return collect(array_keys(self::FRUTAS))
            ->mapWithKeys(fn (string $fruta): array => [$fruta => $this->precoParaFruta($fruta, $itens, $mapeamentosPreco)])
            ->filter()
            ->all();
    }

    private function precoParaFruta(string $fruta, Collection $itens, Collection $mapeamentosPreco): ?array
    {
        $mapeamento = $mapeamentosPreco->get($fruta);
        $itemManual = $mapeamento?->tabela_preco_item_id
            ? $itens->firstWhere('id', $mapeamento->tabela_preco_item_id)
            : null;

        if ($itemManual instanceof TabelaPrecoItem) {
            return $this->dadosPreco($itemManual, 'manual');
        }

        $keywords = [
            'banana' => ['banana'],
            'maca' => ['maca', 'maça', 'maçã'],
            'pera' => ['pera', 'pêra'],
            'laranja' => ['laranja'],
            'kiwi' => ['kiwi'],
            'uvas' => ['uva'],
            'fruta_epoca' => [],
            'frutos_secos' => ['amendoa', 'noz', 'figos secos', 'tâmara'],
            'mirtilos' => ['mirtilo'],
            'framboesas' => ['framboesa'],
            'amoras' => ['amora'],
            'morangos' => ['morango'],
        ][$fruta] ?? [];

        if ($keywords === []) {
            return null;
        }

        $item = $itens->first(function (TabelaPrecoItem $item) use ($keywords): bool {
            $produto = Str::of($item->produto)->lower()->ascii()->toString();

            return collect($keywords)->contains(fn (string $keyword): bool => str_contains($produto, Str::of($keyword)->lower()->ascii()->toString()));
        });

        if ($item === null) {
            return null;
        }

        return $this->dadosPreco($item, 'automatico');
    }

    private function dadosPreco(TabelaPrecoItem $item, string $origem): array
    {
        return [
            'preco' => (float) $item->preco_kg,
            'produto' => $item->produto,
            'fornecedor' => $item->tabelaPreco?->fornecedor,
            'unidade' => $item->unidade,
            'item_id' => $item->id,
            'origem' => $origem,
        ];
    }
}
