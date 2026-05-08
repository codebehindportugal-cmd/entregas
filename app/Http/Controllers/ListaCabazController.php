<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use App\Models\TabelaPreco;
use App\Models\WooOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
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

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'ficheiro' => ['required', 'file', 'max:10240'],
            'publicar' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = json_decode((string) file_get_contents($request->file('ficheiro')->getRealPath()), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON invalido: '.json_last_error_msg());
            }

            $listas = $payload['listas'] ?? null;

            if (! is_array($listas)) {
                throw new RuntimeException('Formato invalido: falta a chave listas.');
            }

            $importadas = 0;
            $itens = 0;

            DB::transaction(function () use ($listas, $request, &$importadas, &$itens): void {
                foreach ($listas as $index => $listaData) {
                    if (! is_array($listaData)) {
                        throw new RuntimeException('Lista '.($index + 1).': esperado objeto/array.');
                    }

                    $lista = ListaCabaz::updateOrCreate(
                        [
                            'semana_numero' => $this->intRange($listaData['semana_numero'] ?? null, 1, 4, 'semana_numero'),
                            'ano' => $this->intRange($listaData['ano'] ?? null, 2000, 2100, 'ano'),
                            'mes' => $this->intRange($listaData['mes'] ?? null, 1, 12, 'mes'),
                        ],
                        [
                            'descricao' => $this->nullableString($listaData['descricao'] ?? null),
                            'estado' => $request->boolean('publicar') ? 'publicada' : $this->estado($listaData['estado'] ?? 'rascunho'),
                        ],
                    );

                    $lista->itens()->delete();

                    foreach ($listaData['itens'] ?? [] as $itemIndex => $itemData) {
                        if (! is_array($itemData)) {
                            throw new RuntimeException('Lista '.($index + 1).', item '.($itemIndex + 1).': esperado objeto/array.');
                        }

                        $lista->itens()->create($this->normalizarItem($itemData, $index + 1, $itemIndex + 1));
                        $itens++;
                    }

                    $importadas++;
                }
            });

            return redirect()->route('lista-cabazes.index')->with('status', "{$importadas} listas importadas com {$itens} produtos.");
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ficheiro' => $exception->getMessage()]);
        }
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

    private function normalizarItem(array $item, int $lista, int $linha): array
    {
        $tipo = (string) ($item['cabaz_tipo'] ?? '');

        if (! array_key_exists($tipo, self::TIPOS)) {
            throw new RuntimeException("Lista {$lista}, item {$linha}: tipo de cabaz invalido.");
        }

        $produto = trim((string) ($item['produto'] ?? ''));

        if ($produto === '') {
            throw new RuntimeException("Lista {$lista}, item {$linha}: produto obrigatorio.");
        }

        $quantidade = (float) ($item['quantidade'] ?? 0);

        if ($quantidade <= 0) {
            throw new RuntimeException("Lista {$lista}, item {$linha}: quantidade invalida.");
        }

        return [
            'cabaz_tipo' => $tipo,
            'produto' => $produto,
            'categoria' => $this->nullableString($item['categoria'] ?? null),
            'quantidade' => $quantidade,
            'unidade' => trim((string) ($item['unidade'] ?? 'un')) ?: 'un',
            'ordem' => max(0, (int) ($item['ordem'] ?? 0)),
        ];
    }

    private function intRange(mixed $value, int $min, int $max, string $campo): int
    {
        $value = (int) $value;

        if ($value < $min || $value > $max) {
            throw new RuntimeException("Campo {$campo} invalido.");
        }

        return $value;
    }

    private function estado(mixed $value): string
    {
        return in_array($value, ['rascunho', 'publicada'], true) ? $value : 'rascunho';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
