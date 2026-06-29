<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\Despesa;
use App\Models\DespesaFoto;
use App\Models\FaturaItem;
use App\Services\PdfProductExtractor;
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
    const CATEGORIAS = [
        'sementes'          => 'Sementes',
        'fertilizantes'     => 'Fertilizantes',
        'fitofarmaceuticos' => 'Fitofarmacêuticos',
        'combustivel'       => 'Combustível',
        'mao_obra'          => 'Mão de obra',
        'equipamento'       => 'Equipamento',
        'outro'             => 'Outro',
    ];

    const TAXAS_IVA = [0, 6, 13, 23];

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $ano = (int) ($request->query('ano') ?: now()->year);
        $mes = (int) ($request->query('mes') ?: now()->month);
        $search = $request->query('search', '');

        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $query = Despesa::query()
            ->with(['items', 'aiJobs' => fn ($query) => $query->latest(), 'capa'])
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
            'notas' => ['nullable', 'string'],
            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'items' => ['nullable', 'array'],
            'items.*.descricao' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantidade' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.unidade_compra' => ['nullable', 'string', 'max:20'],
            'items.*.unidades_por_quantidade' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantidade_unidades' => ['nullable', 'numeric', 'min:0'],
            'items.*.preco_unitario' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.iva_percentagem' => ['required_with:items', 'numeric', 'in:0,6,13,23'],
            'items.*.notas' => ['nullable', 'string'],
        ], $this->validationMessages());

        $despesa = DB::transaction(function () use ($data): Despesa {
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
                'categoria' => 'entrada_produtos',
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

            return $despesa;
        });

        $imagePaths = [];
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $index => $foto) {
                $path = $foto->store('faturas', 'public');
                $despesa->fotos()->create(['path' => $path, 'ordem' => $index]);
                if (str_starts_with($foto->getMimeType() ?? '', 'image/')) {
                    $imagePaths[] = $path;
                }
            }
        }

        $message = 'Entrada registada com sucesso.';
        if (!empty($imagePaths)) {
            $this->queueAiJobs($despesa, $imagePaths);
            $count = count($imagePaths);
            $message = $count === 1
                ? 'Foto recebida. A IA vai processar esta fatura dentro de cerca de 1 minuto.'
                : "{$count} fotos recebidas. A IA vai processar dentro de cerca de 1 minuto.";
        }

        return redirect()->route('despesas.show', $despesa)->with('status', $message);
    }

    public function show(Despesa $despesa): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $despesa->load('items', 'fotos', 'aiJobs');

        return view('despesas.show', ['despesa' => $despesa]);
    }

    public function edit(Despesa $despesa): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $despesa->load('items', 'aiJobs', 'fotos');

        return view('despesas.edit', [
            'despesa' => $despesa,
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
            'notas' => ['nullable', 'string'],
            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'items' => ['nullable', 'array'],
            'items.*.descricao' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantidade' => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.unidade_compra' => ['nullable', 'string', 'max:20'],
            'items.*.unidades_por_quantidade' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantidade_unidades' => ['nullable', 'numeric', 'min:0'],
            'items.*.preco_unitario' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.iva_percentagem' => ['required_with:items', 'numeric', 'in:0,6,13,23'],
            'items.*.notas' => ['nullable', 'string'],
        ], $this->validationMessages());

        DB::transaction(function () use ($data, $despesa): void {
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
                'categoria' => 'entrada_produtos',
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

        $imagePaths = [];
        if ($request->hasFile('fotos')) {
            $nextOrdem = $despesa->fotos()->count();
            foreach ($request->file('fotos') as $index => $foto) {
                $path = $foto->store('faturas', 'public');
                $despesa->fotos()->create(['path' => $path, 'ordem' => $nextOrdem + $index]);
                if (str_starts_with($foto->getMimeType() ?? '', 'image/')) {
                    $imagePaths[] = $path;
                }
            }
        }

        $message = 'Entrada atualizada com sucesso.';
        if (!empty($imagePaths)) {
            $this->queueAiJobs($despesa, $imagePaths);
            $count = count($imagePaths);
            $message = $count === 1
                ? 'Nova foto recebida. A IA vai processar dentro de cerca de 1 minuto.'
                : "{$count} novas fotos recebidas. A IA vai processar dentro de cerca de 1 minuto.";
        }

        return redirect()->route('despesas.show', $despesa)->with('status', $message);
    }

    public function addFoto(Request $request, Despesa $despesa): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'foto' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $file = $request->file('foto');
        $mime = $file->getMimeType() ?? '';
        $path = $file->store('faturas', 'public');
        $foto = $despesa->fotos()->create([
            'path' => $path,
            'ordem' => $despesa->fotos()->count(),
        ]);

        $itemsAdded = 0;

        if ($mime === 'application/pdf') {
            $absolutePath = Storage::disk('public')->path($path);
            $extractor = new PdfProductExtractor();
            $products = $extractor->extractFromPath($absolutePath);

            if (!empty($products)) {
                DB::transaction(function () use ($despesa, $products, &$itemsAdded): void {
                    foreach ($products as $product) {
                        $despesa->items()->create($product);
                        $itemsAdded++;
                    }
                    $despesa->update([
                        'valor' => $despesa->fresh()->total_fatura,
                    ]);
                });
            }
        } elseif (str_starts_with($mime, 'image/')) {
            AiJob::create([
                'despesa_id' => $despesa->id,
                'status' => 'pending',
                'image_path' => $path,
            ]);
        }

        return response()->json([
            'id'          => $foto->id,
            'url'         => $foto->url,
            'pdf'         => $mime === 'application/pdf',
            'items_added' => $itemsAdded,
        ]);
    }

    public function sugestoesItems(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $q = (string) $request->query('q', '');
        $fornecedor = (string) $request->query('fornecedor', '');

        $query = FaturaItem::query()
            ->select('descricao', 'unidade_compra', DB::raw('AVG(preco_unitario) as preco_medio'), DB::raw('MAX(id) as last_id'))
            ->groupBy('descricao', 'unidade_compra')
            ->orderByDesc('last_id')
            ->limit(12);

        if ($q !== '') {
            $query->where('descricao', 'like', "%{$q}%");
        }

        if ($fornecedor !== '') {
            $query->whereHas('despesa', fn ($q) => $q->where('fornecedor', $fornecedor));
        }

        return response()->json(
            $query->get()->map(fn ($r) => [
                'descricao'      => $r->descricao,
                'unidade_compra' => $r->unidade_compra,
                'preco_medio'    => round((float) $r->preco_medio, 4),
            ])->values()
        );
    }

    public function addItem(Request $request, Despesa $despesa): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'descricao'               => ['required', 'string', 'max:255'],
            'quantidade'              => ['required', 'numeric', 'min:0.001'],
            'unidade_compra'          => ['nullable', 'string', 'max:20'],
            'unidades_por_quantidade' => ['nullable', 'numeric', 'min:0'],
            'preco_unitario'          => ['required', 'numeric', 'min:0'],
            'iva_percentagem'         => ['required', 'numeric', 'in:0,6,13,23'],
        ]);

        $item = DB::transaction(function () use ($despesa, $data): FaturaItem {
            $qtdUnid = (float) $data['quantidade'] * (float) ($data['unidades_por_quantidade'] ?? 1);
            $item = $despesa->items()->create([
                'descricao'               => $data['descricao'],
                'quantidade'              => $data['quantidade'],
                'unidade_compra'          => $data['unidade_compra'] ?? 'un',
                'unidades_por_quantidade' => $data['unidades_por_quantidade'] ?? 1,
                'quantidade_unidades'     => $qtdUnid,
                'preco_unitario'          => $data['preco_unitario'],
                'iva_percentagem'         => $data['iva_percentagem'],
            ]);
            $despesa->update(['valor' => $despesa->fresh()->total_fatura]);

            return $item;
        });

        $despesa->refresh();

        return response()->json([
            'item' => [
                'id'                      => $item->id,
                'descricao'               => $item->descricao,
                'quantidade'              => (float) $item->quantidade,
                'unidade_compra'          => $item->unidade_compra,
                'quantidade_unidades'     => (float) $item->quantidade_unidades,
                'custo_unitario'          => $item->custo_unitario,
                'preco_unitario'          => (float) $item->preco_unitario,
                'iva_percentagem'         => (float) $item->iva_percentagem,
                'total_com_iva'           => $item->total_com_iva,
            ],
            'totais' => [
                'subtotal'    => $despesa->subtotal_calculado,
                'iva'         => $despesa->iva_calculado,
                'total'       => $despesa->total_fatura,
            ],
        ]);
    }

    public function deleteFoto(DespesaFoto $foto): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Storage::disk('public')->delete($foto->path);
        $foto->delete();

        return response()->json(['ok' => true]);
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        foreach ($despesa->fotos as $foto) {
            Storage::disk('public')->delete($foto->path);
        }
        if ($despesa->ficheiro_path) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
        }

        $despesa->delete();

        return redirect()->route('despesas.index')->with('status', 'Entrada removida.');
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

    private function queueAiJobs(Despesa $despesa, array $paths): void
    {
        $despesa->aiJobs()
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        foreach ($paths as $path) {
            AiJob::create([
                'despesa_id' => $despesa->id,
                'status' => 'pending',
                'image_path' => $path,
            ]);
        }
    }

    private function validationMessages(): array
    {
        return [
            'fotos.*.uploaded' => 'Uma foto nao conseguiu chegar ao servidor. Tente novamente com uma foto mais leve.',
            'fotos.*.max' => 'Uma foto e demasiado grande. Tente novamente com uma foto mais leve.',
            'fotos.*.mimes' => 'Os ficheiros devem ser JPG, PNG, GIF, WEBP ou PDF.',
        ];
    }
}
