<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\FaturaItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DespesaController extends Controller
{
    const CATEGORIAS = ['combustivel', 'sementes', 'fertilizantes', 'fitofarmaceuticos', 'equipamento', 'mao_obra', 'outro'];

    const TAXAS_IVA = [0, 6, 13, 23];

    const MARCAS = [
        'horta_da_maria' => 'Horta da Maria',
        'extravaganty' => 'Extravaganty',
        'ateneya_geral' => 'Ateneya (geral)',
    ];

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $ano = (int) ($request->query('ano') ?: now()->year);
        $mes = (int) ($request->query('mes') ?: now()->month);
        $categoria = $request->query('categoria', '');
        $marca = $request->query('marca', '');
        $search = $request->query('search', '');

        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $query = Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->when(filled($categoria) && in_array($categoria, self::CATEGORIAS, true), fn ($q) => $q->where('categoria', $categoria))
            ->when(filled($marca) && array_key_exists($marca, self::MARCAS), fn ($q) => $q->where('marca', $marca))
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('fornecedor', 'like', "%{$search}%")
                    ->orWhere('numero_fatura', 'like', "%{$search}%");
            }))
            ->orderBy('data', 'desc')
            ->orderBy('id', 'desc');

        $despesas = $query->paginate(20)->withQueryString();

        // Resumo do mês completo (sem paginação)
        $resumoQuery = Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()]);

        $todasDespesas = $resumoQuery->get();

        $total = $todasDespesas->sum(fn (Despesa $d) => $d->total_fatura);
        $count = $todasDespesas->count();

        $porCategoria = collect(self::CATEGORIAS)->mapWithKeys(function (string $cat) use ($todasDespesas): array {
            return [$cat => $todasDespesas->where('categoria', $cat)->sum(fn (Despesa $d) => $d->total_fatura)];
        })->filter(fn (float $v) => $v > 0)->all();

        $porMarca = collect(array_keys(self::MARCAS))->mapWithKeys(function (string $m) use ($todasDespesas): array {
            return [$m => $todasDespesas->where('marca', $m)->sum(fn (Despesa $d) => $d->total_fatura)];
        })->filter(fn (float $v) => $v > 0)->all();

        $ivaTotal = $todasDespesas->sum(fn (Despesa $d) => $d->iva_calculado);
        $subtotal = $todasDespesas->sum(fn (Despesa $d) => $d->subtotal_calculado);

        $fornecedores = $todasDespesas->groupBy('fornecedor')
            ->map(fn ($group) => $group->sum(fn (Despesa $d) => $d->total_fatura))
            ->filter(fn (float $v) => $v > 0)
            ->sortByDesc(fn ($v) => $v)
            ->take(5);

        $resumo = compact('total', 'count', 'porCategoria', 'porMarca');
        $analytics = ['iva_total' => $ivaTotal, 'subtotal' => $subtotal, 'por_fornecedor' => $fornecedores];

        return view('despesas.index', [
            'despesas' => $despesas,
            'ano' => $ano,
            'mes' => $mes,
            'inicio' => $inicio,
            'categoria' => $categoria,
            'marca' => $marca,
            'search' => $search,
            'categorias' => self::CATEGORIAS,
            'marcas' => self::MARCAS,
            'taxasIva' => self::TAXAS_IVA,
            'resumo' => $resumo,
            'analytics' => $analytics,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('despesas.create', [
            'despesa' => new Despesa,
            'categorias' => self::CATEGORIAS,
            'marcas' => self::MARCAS,
            'taxasIva' => self::TAXAS_IVA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'data' => ['required', 'date'],
            'categoria' => ['required', 'in:'.implode(',', self::CATEGORIAS)],
            'marca' => ['required', 'in:'.implode(',', array_keys(self::MARCAS))],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'items' => ['nullable', 'array'],
            'items.*.descricao' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantidade' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.preco_unitario' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.iva_percentagem' => ['required_with:items', 'numeric', 'in:0,6,13,23'],
            'items.*.notas' => ['nullable', 'string'],
        ]);

        $ficheiroPath = null;
        if ($request->hasFile('ficheiro')) {
            $ficheiroPath = $request->file('ficheiro')->store('despesas', 'public');
        }

        DB::transaction(function () use ($data, $ficheiroPath): void {
            $items = collect($data['items'] ?? []);
            $valorCalculado = $items->isNotEmpty()
                ? $items->sum(fn (array $item) => round((float) $item['quantidade'] * (float) $item['preco_unitario'] * (1 + (float) $item['iva_percentagem'] / 100), 4))
                : (float) ($data['valor'] ?? 0);

            $despesa = Despesa::create([
                'titulo' => $data['titulo'],
                'numero_fatura' => $data['numero_fatura'] ?? null,
                'fornecedor' => $data['fornecedor'] ?? null,
                'valor' => $valorCalculado,
                'data' => $data['data'],
                'categoria' => $data['categoria'],
                'marca' => $data['marca'],
                'ficheiro_path' => $ficheiroPath,
                'notas' => $data['notas'] ?? null,
            ]);

            foreach ($items as $item) {
                $despesa->items()->create([
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'iva_percentagem' => $item['iva_percentagem'],
                    'notas' => $item['notas'] ?? null,
                ]);
            }
        });

        return redirect()->route('despesas.index')->with('status', 'Despesa registada com sucesso.');
    }

    public function edit(Despesa $despesa): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $despesa->load('items');

        return view('despesas.edit', [
            'despesa' => $despesa,
            'categorias' => self::CATEGORIAS,
            'marcas' => self::MARCAS,
            'taxasIva' => self::TAXAS_IVA,
        ]);
    }

    public function update(Request $request, Despesa $despesa): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'data' => ['required', 'date'],
            'categoria' => ['required', 'in:'.implode(',', self::CATEGORIAS)],
            'marca' => ['required', 'in:'.implode(',', array_keys(self::MARCAS))],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'items' => ['nullable', 'array'],
            'items.*.descricao' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantidade' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.preco_unitario' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.iva_percentagem' => ['required_with:items', 'numeric', 'in:0,6,13,23'],
            'items.*.notas' => ['nullable', 'string'],
        ]);

        $ficheiroPath = $despesa->ficheiro_path;
        if ($request->hasFile('ficheiro')) {
            if ($ficheiroPath) {
                Storage::disk('public')->delete($ficheiroPath);
            }
            $ficheiroPath = $request->file('ficheiro')->store('despesas', 'public');
        }

        DB::transaction(function () use ($data, $ficheiroPath, $despesa): void {
            $items = collect($data['items'] ?? []);
            $valorCalculado = $items->isNotEmpty()
                ? $items->sum(fn (array $item) => round((float) $item['quantidade'] * (float) $item['preco_unitario'] * (1 + (float) $item['iva_percentagem'] / 100), 4))
                : (float) ($data['valor'] ?? 0);

            $despesa->update([
                'titulo' => $data['titulo'],
                'numero_fatura' => $data['numero_fatura'] ?? null,
                'fornecedor' => $data['fornecedor'] ?? null,
                'valor' => $valorCalculado,
                'data' => $data['data'],
                'categoria' => $data['categoria'],
                'marca' => $data['marca'],
                'ficheiro_path' => $ficheiroPath,
                'notas' => $data['notas'] ?? null,
            ]);

            $despesa->items()->delete();

            foreach ($items as $item) {
                $despesa->items()->create([
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'iva_percentagem' => $item['iva_percentagem'],
                    'notas' => $item['notas'] ?? null,
                ]);
            }
        });

        return redirect()->route('despesas.index')->with('status', 'Despesa atualizada com sucesso.');
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($despesa->ficheiro_path) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
        }

        $despesa->delete();

        return redirect()->route('despesas.index')->with('status', 'Despesa removida.');
    }

    public function exportarPdf(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $ano = (int) ($request->query('ano') ?: now()->year);
        $mes = (int) ($request->query('mes') ?: now()->month);
        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $despesas = Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data')
            ->get();

        $total = $despesas->sum(fn (Despesa $d) => $d->total_fatura);
        $subtotal = $despesas->sum(fn (Despesa $d) => $d->subtotal_calculado);
        $ivaTotal = $despesas->sum(fn (Despesa $d) => $d->iva_calculado);

        $porCategoria = collect(self::CATEGORIAS)->mapWithKeys(function (string $cat) use ($despesas): array {
            return [$cat => $despesas->where('categoria', $cat)->sum(fn (Despesa $d) => $d->total_fatura)];
        })->filter(fn (float $v) => $v > 0)->all();

        $porMarca = collect(array_keys(self::MARCAS))->mapWithKeys(function (string $m) use ($despesas): array {
            return [$m => $despesas->where('marca', $m)->sum(fn (Despesa $d) => $d->total_fatura)];
        })->filter(fn (float $v) => $v > 0)->all();

        $pdf = Pdf::loadView('despesas.pdf', [
            'despesas' => $despesas,
            'inicio' => $inicio,
            'total' => $total,
            'subtotal' => $subtotal,
            'ivaTotal' => $ivaTotal,
            'porCategoria' => $porCategoria,
            'porMarca' => $porMarca,
            'marcas' => self::MARCAS,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('despesas-'.$inicio->format('Y-m').'.pdf');
    }

    public function exportarCsv(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $ano = (int) ($request->query('ano') ?: now()->year);
        $mes = (int) ($request->query('mes') ?: now()->month);
        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $despesas = Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data')
            ->get();

        $filename = 'despesas-'.$inicio->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($despesas): void {
            $out = fopen('php://output', 'w');
            // BOM para Excel reconhecer UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Data', 'Titulo', 'N. Fatura', 'Fornecedor', 'Marca', 'Categoria', 'Valor Total', 'Descricao Item', 'Qtd', 'Preco Unit.', 'IVA %', 'Total s/ IVA', 'IVA', 'Total c/ IVA'], ';');

            foreach ($despesas as $despesa) {
                if ($despesa->items->isEmpty()) {
                    fputcsv($out, [
                        $despesa->data->format('d/m/Y'),
                        $despesa->titulo,
                        $despesa->numero_fatura ?? '',
                        $despesa->fornecedor ?? '',
                        self::MARCAS[$despesa->marca] ?? $despesa->marca,
                        $despesa->categoria,
                        number_format((float) $despesa->valor, 2, ',', ''),
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    ], ';');
                } else {
                    $first = true;
                    foreach ($despesa->items as $item) {
                        fputcsv($out, [
                            $first ? $despesa->data->format('d/m/Y') : '',
                            $first ? $despesa->titulo : '',
                            $first ? ($despesa->numero_fatura ?? '') : '',
                            $first ? ($despesa->fornecedor ?? '') : '',
                            $first ? (self::MARCAS[$despesa->marca] ?? $despesa->marca) : '',
                            $first ? $despesa->categoria : '',
                            $first ? number_format($despesa->total_fatura, 2, ',', '') : '',
                            $item->descricao,
                            number_format((float) $item->quantidade, 3, ',', ''),
                            number_format((float) $item->preco_unitario, 4, ',', ''),
                            number_format((float) $item->iva_percentagem, 2, ',', '').'%',
                            number_format($item->total_sem_iva, 2, ',', ''),
                            number_format($item->total_iva_valor, 2, ',', ''),
                            number_format($item->total_com_iva, 2, ',', ''),
                        ], ';');
                        $first = false;
                    }
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
