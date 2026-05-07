<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use Illuminate\Http\Request;
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

    public function __invoke(Request $request): View
    {
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
}
