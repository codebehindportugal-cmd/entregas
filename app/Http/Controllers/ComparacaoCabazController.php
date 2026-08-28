<?php

namespace App\Http\Controllers;

use App\Models\CorporateFatura;
use App\Models\Despesa;
use App\Models\WooOrder;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Margens por semana = faturas emitidas (vendas) - entradas/despesas (custos).
 *
 *  Vendas  : CorporateFatura (empresas) + WooOrder com fatura Moloni emitida (B2C).
 *  Custos  : Despesas (entradas de fornecedores).
 *
 * Deixou de depender das listas semanais (removidas).
 */
class ComparacaoCabazController extends Controller
{
    public function __invoke(Request $request): View
    {
        $inicio = filled($request->input('inicio'))
            ? Carbon::parse($request->input('inicio'))->startOfWeek()
            : now()->startOfWeek()->subWeeks(7);
        $fim = filled($request->input('fim'))
            ? Carbon::parse($request->input('fim'))->endOfWeek()
            : now()->endOfWeek();

        if ($fim->lt($inicio)) {
            $fim = $inicio->copy()->endOfWeek();
        }

        // Limite defensivo: no maximo ~1 ano de semanas.
        if ($inicio->diffInWeeks($fim) > 53) {
            $inicio = $fim->copy()->subWeeks(53)->startOfWeek();
        }

        $vendasEmpresas = $this->vendasEmpresasPorSemana($inicio, $fim);
        $vendasB2c = $this->vendasB2cPorSemana($inicio, $fim);
        $entradas = $this->entradasPorSemana($inicio, $fim);

        $semanas = collect(CarbonPeriod::create($inicio->copy(), '1 week', $fim->copy()))
            ->map(function (Carbon $inicioSemana) use ($vendasEmpresas, $vendasB2c, $entradas): array {
                $chave = $inicioSemana->format('o-W');
                $fimSemana = $inicioSemana->copy()->endOfWeek();

                $empresas = (float) ($vendasEmpresas[$chave] ?? 0);
                $b2c = (float) ($vendasB2c[$chave] ?? 0);
                $custo = (float) ($entradas[$chave] ?? 0);
                $faturado = $empresas + $b2c;
                $margem = $faturado - $custo;

                return [
                    'chave' => $chave,
                    'label' => $inicioSemana->format('d/m').' - '.$fimSemana->format('d/m/Y'),
                    'semana_numero' => (int) $inicioSemana->format('W'),
                    'ano' => (int) $inicioSemana->format('o'),
                    'empresas' => round($empresas, 2),
                    'b2c' => round($b2c, 2),
                    'faturado' => round($faturado, 2),
                    'entradas' => round($custo, 2),
                    'margem' => round($margem, 2),
                    'margem_pct' => $faturado > 0 ? round($margem / $faturado * 100, 1) : null,
                ];
            })
            ->values();

        $totais = [
            'empresas' => round($semanas->sum('empresas'), 2),
            'b2c' => round($semanas->sum('b2c'), 2),
            'faturado' => round($semanas->sum('faturado'), 2),
            'entradas' => round($semanas->sum('entradas'), 2),
            'margem' => round($semanas->sum('margem'), 2),
        ];
        $totais['margem_pct'] = $totais['faturado'] > 0
            ? round($totais['margem'] / $totais['faturado'] * 100, 1)
            : null;

        return view('comparacao-cabazes.index', [
            'inicio' => $inicio->format('Y-m-d'),
            'fim' => $fim->format('Y-m-d'),
            'semanas' => $semanas,
            'totais' => $totais,
        ]);
    }

    /** Faturas das empresas (CorporateFatura) agrupadas por semana da emissao. */
    private function vendasEmpresasPorSemana(Carbon $inicio, Carbon $fim): Collection
    {
        return CorporateFatura::query()
            ->whereNotNull('emitida_em')
            ->whereBetween('emitida_em', [$inicio, $fim])
            ->get()
            ->groupBy(fn (CorporateFatura $f): string => Carbon::parse($f->emitida_em)->format('o-W'))
            ->map(fn (Collection $grupo): float => (float) $grupo->sum('total'));
    }

    /** Encomendas B2C com fatura Moloni emitida, por semana da emissao. */
    private function vendasB2cPorSemana(Carbon $inicio, Carbon $fim): Collection
    {
        return WooOrder::query()
            ->whereNotNull('fatura_document_id')
            ->whereNotNull('fatura_emitida_em')
            ->whereBetween('fatura_emitida_em', [$inicio, $fim])
            ->get()
            ->groupBy(fn (WooOrder $o): string => Carbon::parse($o->fatura_emitida_em)->format('o-W'))
            ->map(fn (Collection $grupo): float => (float) $grupo->sum(fn (WooOrder $o): float => (float) $o->total));
    }

    /** Entradas/despesas por semana da data da despesa. */
    private function entradasPorSemana(Carbon $inicio, Carbon $fim): Collection
    {
        return Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->copy()->startOfDay(), $fim->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (Despesa $d): string => Carbon::parse($d->data)->format('o-W'))
            ->map(fn (Collection $grupo): float => (float) $grupo->sum(fn (Despesa $d): float => (float) $d->total_fatura));
    }
}
