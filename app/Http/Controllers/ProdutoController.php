<?php

namespace App\Http\Controllers;

use App\Models\TabelaPrecoItem;
use App\Models\WooProduct;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ProdutoController extends Controller
{
    private const EPOCAS = [
        'Todo o ano',
        'Primavera',
        'Verao',
        'Outono',
        'Inverno',
        'Natal',
    ];

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $estado = $request->string('estado')->toString();
        $syncPage = max(1, $request->integer('sync_page', 1));

        $produtos = WooProduct::query()
            ->with('tabelaPrecoItem.tabelaPreco')
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('woo_id', 'like', "%{$q}%");
            }))
            ->when($estado === 'ativo', fn ($query) => $query
                ->where('status', 'publish')
                ->where('stock_status', 'instock')
                ->where('purchasable', true)
                ->where('disponivel_compra', true)
                ->where('em_epoca', true))
            ->when($estado === 'inativo', fn ($query) => $query->where(fn ($query) => $query
                ->where('status', '!=', 'publish')
                ->orWhereNull('status')
                ->orWhere('stock_status', '!=', 'instock')
                ->orWhereNull('stock_status')
                ->orWhere('purchasable', false)
                ->orWhere('disponivel_compra', false)
                ->orWhere('em_epoca', false)))
            ->when($estado === 'sem_fornecedor', fn ($query) => $query->whereNull('tabela_preco_item_id'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('produtos.index', [
            'produtos' => $produtos,
            'q' => $q,
            'estado' => $estado,
            'syncPage' => $syncPage,
            'epocas' => self::EPOCAS,
            'itensFornecedor' => TabelaPrecoItem::with('tabelaPreco')
                ->whereHas('tabelaPreco', fn ($query) => $query->where('ativa', true))
                ->orderBy('produto')
                ->get(),
        ]);
    }

    public function sync(Request $request, WooCommerceService $service): RedirectResponse
    {
        $page = max(1, $request->integer('sync_page', 1));

        try {
            $result = $service->syncProductsPage($page, 20);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['woocommerce' => $exception->getMessage()]);
        }

        $message = "Produtos sincronizados: pagina {$result['page']}, {$result['fetched']} lidos, {$result['created']} criados, {$result['updated']} atualizados.";

        if ($result['next_page'] !== null) {
            return redirect()
                ->route('produtos.index', ['sync_page' => $result['next_page']])
                ->with('status', $message.' Clica novamente em sincronizar para continuar.');
        }

        return redirect()
            ->route('produtos.index')
            ->with('status', $message.' Sincronizacao concluida.');
    }

    public function update(Request $request, WooProduct $produto, WooCommerceService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:publish,draft,pending,private'],
            'epoca' => ['nullable', 'string', 'max:255'],
            'em_epoca' => ['nullable', 'boolean'],
            'disponivel_compra' => ['nullable', 'boolean'],
            'tabela_preco_item_id' => ['nullable', 'exists:tabela_preco_itens,id'],
            'custo_quantidade' => ['required', 'numeric', 'min:0'],
            'custo_unidade' => ['required', 'string', 'max:20'],
        ]);

        $produto->update([
            ...$data,
            'regular_price' => filled($data['regular_price'] ?? null) ? (float) $data['regular_price'] : null,
            'sale_price' => filled($data['sale_price'] ?? null) ? (float) $data['sale_price'] : null,
            'epoca' => filled($data['epoca'] ?? null) ? $data['epoca'] : null,
            'em_epoca' => $request->boolean('em_epoca'),
            'disponivel_compra' => $request->boolean('disponivel_compra'),
            'tabela_preco_item_id' => filled($data['tabela_preco_item_id'] ?? null) ? (int) $data['tabela_preco_item_id'] : null,
        ]);

        if ($request->boolean('sync_site')) {
            try {
                $service->updateProductFromLocal($produto->fresh());
            } catch (RuntimeException $exception) {
                return back()->withErrors(['woocommerce' => $exception->getMessage()]);
            }

            return back()->with('status', 'Produto guardado e atualizado no site.');
        }

        return back()->with('status', 'Produto guardado.');
    }

    public function updateSite(WooProduct $produto, WooCommerceService $service): RedirectResponse
    {
        try {
            $service->updateProductFromLocal($produto);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['woocommerce' => $exception->getMessage()]);
        }

        return back()->with('status', 'Produto atualizado no site.');
    }
}
