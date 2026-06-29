<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use App\Models\WooOrder;
use App\Services\ComprasService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ComparacaoCabazController extends Controller
{
    private const TIPOS = [
        'mini' => 'Mini',
        'pequeno' => 'Pequeno',
        'medio' => 'Medio',
        'grande' => 'Grande',
    ];

    public function __invoke(Request $request, ComprasService $compras): View
    {
        $inicio = filled($request->input('inicio')) ? Carbon::parse($request->input('inicio')) : now()->startOfWeek();
        $fim = filled($request->input('fim')) ? Carbon::parse($request->input('fim')) : $inicio->copy()->endOfWeek();

        if ($fim->lt($inicio)) {
            $fim = $inicio->copy();
        }

        if ($inicio->diffInDays($fim) > 31) {
            $fim = $inicio->copy()->addDays(31);
        }

        $listas = ListaCabaz::query()
            ->with('itens.tabelaPrecoItem')
            ->orderByDesc('ano')
            ->orderByDesc('mes')
            ->orderByDesc('semana_numero')
            ->get();
        $lista = $listas->firstWhere('id', (int) $request->integer('lista_cabaz_id')) ?? $listas->first();
        $empresas = Corporate::query()
            ->where('ativo', true)
            ->whereNotNull('cabaz_tipo')
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get();
        $linhasEmpresas = $this->linhasEmpresas($inicio, $fim, $compras);

        $resumoSubscritores = $lista ? $this->resumoSubscritores($lista) : [];

        return view('comparacao-cabazes.index', [
            'listas' => $listas,
            'lista' => $lista,
            'tipos' => self::TIPOS,
            'linhas' => $lista ? $this->linhas($lista, $empresas) : collect(),
            'resumo' => $lista ? $this->resumo($lista, $empresas) : [
                'cabazes' => 0,
                'custo' => 0,
                'venda' => 0,
                'margem' => 0,
                'margem_percentagem' => null,
            ],
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'linhasEmpresas' => $linhasEmpresas,
            'resumoEmpresas' => $this->resumoEmpresas($linhasEmpresas),
            'resumoSubscritores' => $resumoSubscritores,
            'precosPorCabaz' => ListaCabaz::precosPorCabaz(),
            'custoMaxPorCabaz' => ListaCabaz::custoMaxPorCabaz(),
        ]);
    }

    private function linhas(ListaCabaz $lista, Collection $empresas): Collection
    {
        $custos = $this->custosPorTipo($lista);
        $pecas = $this->pecasPorTipo($lista);

        return $empresas
            ->filter(fn (Corporate $corporate): bool => isset(self::TIPOS[$corporate->cabaz_tipo]))
            ->map(function (Corporate $corporate) use ($custos, $pecas): array {
                $quantidade = max(1, (int) ($corporate->cabaz_quantidade ?? 1));
                $tipo = (string) $corporate->cabaz_tipo;
                $custoCabaz = (float) ($custos[$tipo] ?? 0);
                $pecasCabaz = (float) ($pecas[$tipo] ?? 0);
                $precoPeca = $corporate->preco_venda_peca !== null ? (float) $corporate->preco_venda_peca : null;
                $custo = round($custoCabaz * $quantidade, 2);
                $venda = $precoPeca !== null ? round($pecasCabaz * $precoPeca * $quantidade, 2) : null;

                return [
                    'empresa' => $corporate,
                    'tipo' => $tipo,
                    'tipo_label' => self::TIPOS[$tipo],
                    'quantidade' => $quantidade,
                    'pecas_cabaz' => $pecasCabaz,
                    'preco_peca' => $precoPeca,
                    'custo_cabaz' => $custoCabaz,
                    'venda_cabaz' => $precoPeca !== null ? round($pecasCabaz * $precoPeca, 2) : null,
                    'custo' => $custo,
                    'venda' => $venda,
                    'margem' => $venda !== null ? round($venda - $custo, 2) : null,
                ];
            })
            ->values();
    }

    private function resumo(ListaCabaz $lista, Collection $empresas): array
    {
        $linhas = $this->linhas($lista, $empresas);
        $custo = round($linhas->sum('custo'), 2);
        $venda = round($linhas->sum(fn (array $linha): float => (float) ($linha['venda'] ?? 0)), 2);
        $margem = round($venda - $custo, 2);

        return [
            'cabazes' => $linhas->sum('quantidade'),
            'custo' => $custo,
            'venda' => $venda,
            'margem' => $margem,
            'margem_percentagem' => $venda > 0 ? round(($margem / $venda) * 100, 1) : null,
        ];
    }

    private function resumoSubscritores(ListaCabaz $lista): array
    {
        $custos = $this->custosPorTipo($lista);
        $precos = ListaCabaz::precosPorCabaz();
        $custoMax = ListaCabaz::custoMaxPorCabaz();

        $linhas = collect(self::TIPOS)->map(function (string $label, string $tipo) use ($custos, $precos, $custoMax): array {
            $subscritores = WooOrder::query()
                ->where('source_type', 'subscription')
                ->where('cabaz_tipo', $tipo)
                ->whereIn('status', ['active', 'subscricao', 'wc-subscricao', 'processing', 'on-hold'])
                ->count();

            $custoCabaz = (float) ($custos[$tipo] ?? 0);
            $precoCabaz = (float) ($precos[$tipo] ?? 0);
            $custoMaxCabaz = (float) ($custoMax[$tipo] ?? 0);
            $custo = round($custoCabaz * $subscritores, 2);
            $venda = round($precoCabaz * $subscritores, 2);
            $margem = round($venda - $custo, 2);

            return [
                'tipo'          => $tipo,
                'tipo_label'    => $label,
                'subscritores'  => $subscritores,
                'custo_cabaz'   => $custoCabaz,
                'preco_cabaz'   => $precoCabaz,
                'custo_max'     => $custoMaxCabaz,
                'custo'         => $custo,
                'venda'         => $venda,
                'margem'        => $margem,
                'margem_pct'    => $venda > 0 ? round(($margem / $venda) * 100, 1) : null,
                'dentro_target' => $custoCabaz <= $custoMaxCabaz,
            ];
        })->values();

        $custo = round($linhas->sum('custo'), 2);
        $venda = round($linhas->sum('venda'), 2);
        $margem = round($venda - $custo, 2);

        return [
            'linhas'             => $linhas,
            'total_subscritores' => $linhas->sum('subscritores'),
            'custo'              => $custo,
            'venda'              => $venda,
            'margem'             => $margem,
            'margem_pct'         => $venda > 0 ? round(($margem / $venda) * 100, 1) : null,
        ];
    }

    private function custosPorTipo(ListaCabaz $lista): array
    {
        return collect(self::TIPOS)
            ->mapWithKeys(fn (string $label, string $tipo): array => [
                $tipo => $lista->itens
                    ->where('cabaz_tipo', $tipo)
                    ->sum(fn (ListaCabazItem $item): float => (float) ($item->custoUnitario() ?? 0)),
            ])
            ->all();
    }

    private function pecasPorTipo(ListaCabaz $lista): array
    {
        return collect(self::TIPOS)
            ->mapWithKeys(fn (string $label, string $tipo): array => [
                $tipo => $lista->itens
                    ->where('cabaz_tipo', $tipo)
                    ->filter(fn (ListaCabazItem $item): bool => $this->unidadeContaComoPeca($item->unidade))
                    ->sum(fn (ListaCabazItem $item): float => (float) $item->quantidade),
            ])
            ->all();
    }

    private function unidadeContaComoPeca(?string $unidade): bool
    {
        $unidade = mb_strtolower(trim((string) $unidade));

        return ! in_array($unidade, ['kg', 'g', 'gr', 'gramas'], true);
    }

    private function linhasEmpresas(Carbon $inicio, Carbon $fim, ComprasService $compras): Collection
    {
        $precos = $compras->precosParaData($inicio->copy());

        return Corporate::query()
            ->where('ativo', true)
            ->whereNull('cabaz_tipo')
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get()
            ->map(function (Corporate $corporate) use ($inicio, $fim, $precos): array {
                $entregas = 0;
                $pecas = 0;
                $custo = 0.0;

                foreach (CarbonPeriod::create($inicio, $fim) as $data) {
                    $dia = ComprasService::DIAS[$data->dayOfWeek] ?? null;

                    if ($dia === null || ! in_array($dia, $corporate->dias_entrega ?? [], true) || ! $corporate->temEntregaNaData($data)) {
                        continue;
                    }

                    $entregas++;
                    $frutas = $corporate->frutasParaDia($dia);
                    $pecas += (int) array_sum(collect($frutas)->except(ComprasService::PRODUTOS_KG)->all());

                    foreach ($frutas as $fruta => $quantidade) {
                        $kg = in_array($fruta, ComprasService::PRODUTOS_KG, true)
                            ? (float) $quantidade
                            : (float) $quantidade * (ComprasService::PESOS_PADRAO[$fruta] ?? 1);
                        $custo += $kg * ($precos[$fruta]['preco'] ?? 0);
                    }
                }

                $precoPeca = $corporate->preco_venda_peca !== null ? (float) $corporate->preco_venda_peca : null;
                $venda = $precoPeca !== null ? round($pecas * $precoPeca, 2) : null;
                $custo = round($custo, 2);

                return [
                    'empresa' => $corporate,
                    'entregas' => $entregas,
                    'pecas' => $pecas,
                    'preco_peca' => $precoPeca,
                    'custo' => $custo,
                    'venda' => $venda,
                    'margem' => $venda !== null ? round($venda - $custo, 2) : null,
                ];
            })
            ->filter(fn (array $linha): bool => $linha['entregas'] > 0)
            ->values();
    }

    private function resumoEmpresas(Collection $linhas): array
    {
        $custo = round($linhas->sum('custo'), 2);
        $venda = round($linhas->sum(fn (array $linha): float => (float) ($linha['venda'] ?? 0)), 2);
        $margem = round($venda - $custo, 2);

        return [
            'empresas' => $linhas->count(),
            'entregas' => $linhas->sum('entregas'),
            'pecas' => $linhas->sum('pecas'),
            'custo' => $custo,
            'venda' => $venda,
            'margem' => $margem,
            'margem_percentagem' => $venda > 0 ? round(($margem / $venda) * 100, 1) : null,
        ];
    }
}
