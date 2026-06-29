<?php

namespace App\Http\Controllers;

use App\Models\FaturaItem;
use App\Models\WooProduct;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ProdutoController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $fornecedor = $request->string('fornecedor')->toString();

        $produtos = FaturaItem::query()
            ->join('despesas', 'despesas.id', '=', 'fatura_items.despesa_id')
            ->select([
                'fatura_items.descricao',
                'fatura_items.unidade_compra',
                DB::raw('COUNT(*) as total_linhas'),
                DB::raw('AVG(fatura_items.preco_unitario) as preco_medio'),
                DB::raw('MIN(fatura_items.preco_unitario) as preco_min'),
                DB::raw('MAX(fatura_items.preco_unitario) as preco_max'),
                DB::raw('SUM(fatura_items.quantidade) as quantidade_total'),
                DB::raw('MAX(fatura_items.created_at) as ultima_compra'),
                DB::raw('GROUP_CONCAT(DISTINCT despesas.fornecedor ORDER BY despesas.fornecedor SEPARATOR \', \') as fornecedores'),
            ])
            ->when(filled($q), fn ($query) => $query->where('fatura_items.descricao', 'like', "%{$q}%"))
            ->when(filled($fornecedor), fn ($query) => $query->where('despesas.fornecedor', 'like', "%{$fornecedor}%"))
            ->groupBy('fatura_items.descricao', 'fatura_items.unidade_compra')
            ->orderBy('fatura_items.descricao')
            ->paginate(50)
            ->withQueryString();

        $fornecedores = DB::table('despesas')
            ->whereNotNull('fornecedor')
            ->where('fornecedor', '!=', '')
            ->distinct()
            ->orderBy('fornecedor')
            ->pluck('fornecedor');

        return view('produtos.index', [
            'produtos' => $produtos,
            'q' => $q,
            'fornecedor' => $fornecedor,
            'fornecedores' => $fornecedores,
        ]);
    }

    public function sync(Request $request, WooCommerceService $service): RedirectResponse
    {
        $data = $request->validate([
            'sync_fields' => ['nullable', 'array'],
            'sync_fields.*' => ['string', 'in:identity,prices,images,description,short_description,availability,metadata'],
        ]);
        $page = max(1, $request->integer('sync_page', 1));
        $fields = $data['sync_fields'] ?? [];

        try {
            $result = $service->syncProductsPage($page, 20, $fields);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['woocommerce' => $exception->getMessage()]);
        }

        $message = "Produtos sincronizados: pagina {$result['page']}, {$result['fetched']} lidos, {$result['created']} criados, {$result['updated']} atualizados.";

        if ($result['next_page'] !== null) {
            return redirect()
                ->route('produtos.index', [
                    'sync_page' => $result['next_page'],
                    'sync_fields' => $fields,
                ])
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
                $service->updateProductFromLocal($produto->fresh(), $request->input('sync_fields', []));
            } catch (RuntimeException $exception) {
                return back()->withErrors(['woocommerce' => $exception->getMessage()]);
            }

            return back()->with('status', 'Produto guardado e atualizado no site.');
        }

        return back()->with('status', 'Produto guardado.');
    }

    public function updateSite(Request $request, WooProduct $produto, WooCommerceService $service): RedirectResponse
    {
        $data = $request->validate([
            'sync_fields' => ['nullable', 'array'],
            'sync_fields.*' => ['string', 'in:identity,prices,images,description,short_description,availability,metadata'],
        ]);

        try {
            $service->updateProductFromLocal($produto, $data['sync_fields'] ?? []);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['woocommerce' => $exception->getMessage()]);
        }

        return back()->with('status', 'Produto atualizado no site.');
    }
}
