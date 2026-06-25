<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\FaturaItem;
use App\Services\FaturaAiExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
        $search = $request->query('search', '');

        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $query = Despesa::query()
            ->with('items')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
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

        $ivaTotal = $todasDespesas->sum(fn (Despesa $d) => $d->iva_calculado);
        $subtotal = $todasDespesas->sum(fn (Despesa $d) => $d->subtotal_calculado);

        $fornecedores = $todasDespesas->groupBy('fornecedor')
            ->map(fn ($group) => $group->sum(fn (Despesa $d) => $d->total_fatura))
            ->filter(fn (float $v) => $v > 0)
            ->sortByDesc(fn ($v) => $v)
            ->take(5);

        $resumo = compact('total', 'count');
        $analytics = ['iva_total' => $ivaTotal, 'subtotal' => $subtotal, 'por_fornecedor' => $fornecedores];

        return view('despesas.index', [
            'despesas' => $despesas,
            'ano' => $ano,
            'mes' => $mes,
            'inicio' => $inicio,
            'search' => $search,
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

    public function extrairIa(Request $request, FaturaAiExtractor $extractor): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'ficheiro' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'ficheiro.required' => 'Escolha ou tire uma foto da fatura.',
            'ficheiro.uploaded' => 'A foto nao conseguiu chegar ao servidor. No prod, confirme upload_max_filesize, post_max_size e client_max_body_size.',
            'ficheiro.max' => 'A foto e demasiado grande. Tente novamente com uma foto mais leve.',
            'ficheiro.mimes' => 'A extracao por IA aceita JPG, PNG ou WEBP.',
        ]);

        try {
            return response()->json($extractor->extract($data['ficheiro']));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Erro ao extrair fatura com IA', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel extrair a fatura com IA. Verifique a chave da OpenAI e tente novamente.',
            ], 500);
        }
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
            'items.*.unidade_compra' => ['nullable', 'string', 'max:20'],
            'items.*.unidades_por_quantidade' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantidade_unidades' => ['nullable', 'numeric', 'min:0'],
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
                    'unidade_compra' => $item['unidade_compra'] ?? 'un',
                    'unidades_por_quantidade' => $item['unidades_por_quantidade'] ?? 1,
                    'quantidade_unidades' => $item['quantidade_unidades'] ?? ((float) $item['quantidade'] * (float) ($item['unidades_por_quantidade'] ?? 1)),
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
            'items.*.unidade_compra' => ['nullable', 'string', 'max:20'],
            'items.*.unidades_por_quantidade' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantidade_unidades' => ['nullable', 'numeric', 'min:0'],
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
                    'unidade_compra' => $item['unidade_compra'] ?? 'un',
                    'unidades_por_quantidade' => $item['unidades_por_quantidade'] ?? 1,
                    'quantidade_unidades' => $item['quantidade_unidades'] ?? ((float) $item['quantidade'] * (float) ($item['unidades_por_quantidade'] ?? 1)),
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

        $pdf = Pdf::loadView('despesas.pdf', [
            'despesas' => $despesas,
            'inicio' => $inicio,
            'total' => $total,
            'subtotal' => $subtotal,
            'ivaTotal' => $ivaTotal,
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

            fputcsv($out, ['Data', 'Titulo', 'N. Fatura', 'Fornecedor', 'Valor Total', 'Descricao Item', 'Qtd compra', 'Unidade compra', 'Unid./qtd.', 'Qtd unidades', 'Custo/unid. s/ IVA', 'Preco Unit.', 'IVA %', 'Total s/ IVA', 'IVA', 'Total c/ IVA'], ';');

            foreach ($despesas as $despesa) {
                if ($despesa->items->isEmpty()) {
                    fputcsv($out, [
                        $despesa->data->format('d/m/Y'),
                        $despesa->titulo,
                        $despesa->numero_fatura ?? '',
                        $despesa->fornecedor ?? '',
                        number_format((float) $despesa->valor, 2, ',', ''),
                        '',
                        '',
                        '',
                        '',
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
                            $first ? number_format($despesa->total_fatura, 2, ',', '') : '',
                            $item->descricao,
                            number_format((float) $item->quantidade, 3, ',', ''),
                            $item->unidade_compra,
                            number_format((float) $item->unidades_por_quantidade, 3, ',', ''),
                            number_format((float) $item->quantidade_unidades, 3, ',', ''),
                            $item->custo_unitario !== null ? number_format($item->custo_unitario, 4, ',', '') : '',
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
