<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use App\Models\TabelaPreco;
use App\Models\WooOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ListaCabazController extends Controller
{
    private const TIPOS = [
        'mini' => 'Mini',
        'pequeno' => 'Pequeno',
        'medio' => 'Medio',
        'grande' => 'Grande',
    ];

    public function index(): View
    {
        return view('lista-cabazes.index', [
            'listas' => ListaCabaz::query()
                ->orderByDesc('ano')
                ->orderByDesc('mes')
                ->orderByDesc('semana_numero')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('lista-cabazes.create', [
            'anos' => range(now()->year - 1, now()->year + 1),
            'meses' => ListaCabaz::meses(),
            'semanas' => range(1, 4),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semana_numero' => ['required', 'integer', 'min:1', 'max:4'],
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        $lista = ListaCabaz::create($data);

        return redirect()->route('lista-cabazes.edit', $lista)->with('status', 'Lista semanal criada.');
    }

    public function edit(ListaCabaz $listaCabaz): View
    {
        $listaCabaz->load('itens');
        $tabelasPrecos = TabelaPreco::ativa()->with('itens')->get();

        return view('lista-cabazes.edit', [
            'listaCabaz' => $listaCabaz,
            'tipos' => self::TIPOS,
            'itensPorTipo' => $listaCabaz->itens->sortBy([['categoria', 'asc'], ['ordem', 'asc']])->groupBy('cabaz_tipo'),
            'contagens' => $this->contagensPorTipo(),
            'tabelasPrecos' => $tabelasPrecos,
            'precoItens' => $tabelasPrecos->flatMap(fn (TabelaPreco $tabela) => $tabela->itens)->sortBy('produto')->values(),
            'custoPorTipo' => $this->custoPorTipo($listaCabaz->itens),
        ]);
    }

    public function update(Request $request, ListaCabaz $listaCabaz): RedirectResponse
    {
        $data = $request->validate([
            'descricao' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:rascunho,publicada'],
        ]);

        $listaCabaz->update($data);

        return back()->with('status', 'Lista atualizada.');
    }

    public function destroy(ListaCabaz $listaCabaz): RedirectResponse
    {
        $listaCabaz->delete();

        return redirect()->route('lista-cabazes.index')->with('status', 'Lista removida.');
    }

    public function storeItem(Request $request, ListaCabaz $listaCabaz): RedirectResponse
    {
        $data = $this->validateItem($request);

        $listaCabaz->itens()->create($data);

        return redirect()->route('lista-cabazes.edit', $listaCabaz)->with('status', 'Produto adicionado.');
    }

    public function updateItem(Request $request, ListaCabazItem $item): RedirectResponse
    {
        $item->update($this->validateItem($request));

        return redirect()->route('lista-cabazes.edit', $item->listaCabaz)->with('status', 'Produto atualizado.');
    }

    public function destroyItem(ListaCabazItem $item): RedirectResponse
    {
        $lista = $item->listaCabaz;
        $item->delete();

        return redirect()->route('lista-cabazes.edit', $lista)->with('status', 'Produto removido.');
    }

    public function totais(Request $request, ListaCabaz $listaCabaz): View
    {
        $listaCabaz->load('itens');
        $contagens = $this->contagensPorTipo();
        $totais = $this->totaisComprar($listaCabaz->itens, $contagens);

        return view('lista-cabazes.totais', [
            'listaCabaz' => $listaCabaz,
            'tipos' => self::TIPOS,
            'contagens' => $contagens,
            'totais' => $totais,
        ]);
    }

    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'cabaz_tipo' => ['required', 'in:mini,pequeno,medio,grande'],
            'produto' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['required', 'string', 'max:20'],
            'tabela_preco_item_id' => ['nullable', 'exists:tabela_preco_itens,id'],
            'preco_unitario' => ['nullable', 'numeric', 'min:0'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['tabela_preco_item_id'] = filled($data['tabela_preco_item_id'] ?? null) ? $data['tabela_preco_item_id'] : null;
        $data['preco_unitario'] = filled($data['preco_unitario'] ?? null) ? $data['preco_unitario'] : null;

        return $data;
    }

    private function contagensPorTipo(): array
    {
        return collect(self::TIPOS)
            ->mapWithKeys(fn (string $label, string $tipo): array => [
                $tipo => [
                    'subscritores' => WooOrder::query()
                        ->where('source_type', 'subscription')
                        ->where('cabaz_tipo', $tipo)
                        ->whereIn('status', ['active', 'subscricao', 'wc-subscricao', 'processing', 'on-hold'])
                        ->count(),
                    'empresas' => Corporate::query()
                        ->where('ativo', true)
                        ->where('cabaz_tipo', $tipo)
                        ->sum('cabaz_quantidade'),
                ],
            ])
            ->all();
    }

    private function totaisComprar(Collection $itens, array $contagens): Collection
    {
        return $itens
            ->groupBy(fn (ListaCabazItem $item): string => mb_strtolower($item->produto).'|'.mb_strtolower((string) $item->categoria).'|'.$item->unidade)
            ->map(function (Collection $items) use ($contagens): array {
                $primeiro = $items->first();
                $porTipo = collect(self::TIPOS)->mapWithKeys(function (string $label, string $tipo) use ($items, $contagens): array {
                    $cabazes = (int) ($contagens[$tipo]['subscritores'] ?? 0) + (int) ($contagens[$tipo]['empresas'] ?? 0);
                    $quantidade = $items
                        ->where('cabaz_tipo', $tipo)
                        ->sum(fn (ListaCabazItem $item): float => (float) $item->quantidade * $cabazes);
                    $custo = $items
                        ->where('cabaz_tipo', $tipo)
                        ->sum(fn (ListaCabazItem $item): float => (float) ($item->custoUnitario() ?? 0) * $cabazes);

                    return [$tipo => ['quantidade' => $quantidade, 'custo' => $custo]];
                });

                return [
                    'produto' => $primeiro->produto,
                    'categoria' => $primeiro->categoria,
                    'unidade' => $primeiro->unidade,
                    'por_tipo' => $porTipo->all(),
                    'total' => $porTipo->sum('quantidade'),
                    'custo_total' => $porTipo->sum('custo'),
                ];
            })
            ->sortBy('produto')
            ->values();
    }

    private function custoPorTipo(Collection $itens): array
    {
        return collect(self::TIPOS)
            ->mapWithKeys(fn (string $label, string $tipo): array => [
                $tipo => $itens
                    ->where('cabaz_tipo', $tipo)
                    ->sum(fn (ListaCabazItem $item): float => (float) ($item->custoUnitario() ?? 0)),
            ])
            ->all();
    }
}
