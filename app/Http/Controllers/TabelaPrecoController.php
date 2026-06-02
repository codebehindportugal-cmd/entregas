<?php

namespace App\Http\Controllers;

use App\Models\TabelaPreco;
use App\Models\TabelaPrecoItem;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\View\View;

class TabelaPrecoController extends Controller
{
    public const CATEGORIAS = [
        'Frutas Exoticas',
        'Frutas',
        'Citrinos',
        'Macas',
        'Peras',
        'Legumes',
        'Cogumelos',
        'Batatas',
        'Cozidos e Embalados',
        'Secos',
    ];

    public const EPOCAS = [
        'Todo o ano',
        'Primavera',
        'Verao',
        'Outono',
        'Inverno',
        'Natal',
    ];

    public function index(): View
    {
        return view('tabelas-precos.index', [
            'tabelas' => TabelaPreco::withCount('itens')->orderByDesc('valida_de')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('tabelas-precos.create', [
            'tabelaPreco' => new TabelaPreco([
                'fornecedor' => 'Sentido da Fruta',
                'valida_de' => now(),
                'ativa' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tabela = TabelaPreco::create($this->validateHeader($request));

        return redirect()->route('tabelas-precos.show', $tabela)->with('status', 'Tabela de precos criada.');
    }

    public function show(Request $request, TabelaPreco $tabelaPreco): View
    {
        $q = $request->string('q')->toString();
        $itens = $tabelaPreco->itens()
            ->when(filled($q), fn ($query) => $query->where('produto', 'like', "%{$q}%"))
            ->orderBy('categoria')
            ->orderBy('ordem')
            ->orderBy('produto')
            ->get()
            ->groupBy('categoria');

        return view('tabelas-precos.show', [
            'tabelaPreco' => $tabelaPreco,
            'categorias' => self::CATEGORIAS,
            'epocas' => self::EPOCAS,
            'itensPorCategoria' => $itens,
            'q' => $q,
        ]);
    }

    public function edit(TabelaPreco $tabelaPreco): View
    {
        return view('tabelas-precos.edit', compact('tabelaPreco'));
    }

    public function update(Request $request, TabelaPreco $tabelaPreco): RedirectResponse
    {
        $tabelaPreco->update($this->validateHeader($request));

        return redirect()->route('tabelas-precos.show', $tabelaPreco)->with('status', 'Tabela de precos atualizada.');
    }

    public function destroy(TabelaPreco $tabelaPreco): RedirectResponse
    {
        $tabelaPreco->delete();

        return redirect()->route('tabelas-precos.index')->with('status', 'Tabela de precos eliminada.');
    }

    public function manual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fornecedor' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        $tabela = TabelaPreco::create([
            'fornecedor' => $data['fornecedor'],
            'descricao' => $data['descricao'] ?: 'Tabela manual',
            'valida_de' => now()->toDateString(),
            'valida_ate' => null,
            'ativa' => true,
        ]);

        return redirect()->route('tabelas-precos.show', $tabela)->with('status', 'Tabela manual criada. Adicione apenas os produtos desse produtor.');
    }

    public function storeItem(Request $request, TabelaPreco $tabelaPreco): RedirectResponse
    {
        $tabelaPreco->itens()->create($this->validateItem($request));

        return back()->with('status', 'Produto adicionado.');
    }

    public function updateItem(Request $request, TabelaPrecoItem $item, WooCommerceService $service): RedirectResponse
    {
        $item->update($this->validateItem($request));

        if ($request->boolean('sync_site')) {
            try {
                $service->updateProductFromTabelaItem($item->fresh());
            } catch (RuntimeException $exception) {
                return back()->withErrors(['woocommerce' => $exception->getMessage()]);
            }

            return back()->with('status', 'Produto guardado e atualizado no site.');
        }

        return back()->with('status', 'Produto atualizado.');
    }

    public function syncItem(TabelaPrecoItem $item, WooCommerceService $service): RedirectResponse
    {
        try {
            $service->updateProductFromTabelaItem($item->fresh());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['woocommerce' => $exception->getMessage()]);
        }

        return back()->with('status', 'Produto atualizado no site.');
    }

    public function destroyItem(TabelaPrecoItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', 'Produto removido.');
    }

    public function clonar(TabelaPreco $tabelaPreco): RedirectResponse
    {
        $nova = $tabelaPreco->replicate(['valida_de', 'valida_ate', 'ativa']);
        $nova->forceFill([
            'descricao' => trim(($tabelaPreco->descricao ?: 'Tabela').' - copia'),
            'valida_de' => now()->toDateString(),
            'valida_ate' => null,
            'ativa' => false,
        ])->save();

        $tabelaPreco->itens->each(fn (TabelaPrecoItem $item) => $nova->itens()->create($item->only([
            'categoria',
            'produto',
            'origem',
            'calibre',
            'preco_kg',
            'preco_kg_iva',
            'unidade',
            'notas',
            'ordem',
            'epoca',
            'em_epoca',
            'disponivel_compra',
            'woo_product_id',
            'woo_variation_id',
        ])));

        return redirect()->route('tabelas-precos.edit', $nova)->with('status', 'Tabela clonada.');
    }

    private function validateHeader(Request $request): array
    {
        return [
            ...$request->validate([
                'fornecedor' => ['required', 'string', 'max:255'],
                'descricao' => ['nullable', 'string', 'max:255'],
                'valida_de' => ['required', 'date'],
                'valida_ate' => ['nullable', 'date', 'after_or_equal:valida_de'],
                'ativa' => ['nullable', 'boolean'],
            ]),
            'ativa' => $request->boolean('ativa'),
        ];
    }

    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'categoria' => ['required', 'in:'.implode(',', self::CATEGORIAS)],
            'produto' => ['required', 'string', 'max:255'],
            'origem' => ['nullable', 'string', 'max:255'],
            'calibre' => ['nullable', 'string', 'max:255'],
            'epoca' => ['nullable', 'string', 'max:255'],
            'em_epoca' => ['nullable', 'boolean'],
            'disponivel_compra' => ['nullable', 'boolean'],
            'woo_product_id' => ['nullable', 'integer', 'min:1'],
            'woo_variation_id' => ['nullable', 'integer', 'min:1'],
            'preco_kg' => ['required', 'numeric', 'min:0'],
            'preco_kg_iva' => ['required', 'numeric', 'min:0'],
            'unidade' => ['required', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            ...$data,
            'epoca' => filled($data['epoca'] ?? null) ? $data['epoca'] : null,
            'em_epoca' => $request->boolean('em_epoca'),
            'disponivel_compra' => $request->boolean('disponivel_compra'),
            'woo_product_id' => filled($data['woo_product_id'] ?? null) ? (int) $data['woo_product_id'] : null,
            'woo_variation_id' => filled($data['woo_variation_id'] ?? null) ? (int) $data['woo_variation_id'] : null,
        ];
    }
}
