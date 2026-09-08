<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\Despesa;
use App\Services\FaturaAiExtractor;
use App\Services\PaperInvoice\PaperInvoiceExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            ->with(['items', 'aiJobs' => fn ($query) => $query->latest()])
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

    public function extrairIa(Request $request, FaturaAiExtractor $extractor): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'ficheiro' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'ficheiro.required' => 'Escolha uma foto ou um PDF da fatura.',
            'ficheiro.uploaded' => 'O ficheiro nao conseguiu chegar ao servidor. Confirme upload_max_filesize, post_max_size e client_max_body_size.',
            'ficheiro.max' => 'O ficheiro e demasiado grande. Tente novamente com uma foto mais leve.',
            'ficheiro.mimes' => 'A leitura por IA aceita JPG, PNG, WEBP ou PDF.',
        ]);

        // 1) Leitura local (pdftotext / tesseract / zbarimg), sem depender de APIs.
        $local = $this->lerFaturaLocalmente($data['ficheiro']);

        if ($local !== null && $local['items'] !== []) {
            return response()->json($local);
        }

        // 2) Sem linhas pelo OCR: tenta a IA da OpenAI.
        try {
            $extraido = $extractor->extract($data['ficheiro']);
            $extraido['items'] = $this->normalizarItensIa($extraido['items'] ?? []);
            $extraido['fonte'] = 'openai';

            return response()->json($extraido);
        } catch (Throwable $exception) {
            $motivo = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Nao foi possivel ler a fatura com IA.';

            if (! $exception instanceof RuntimeException) {
                Log::error('Erro ao ler fatura com IA', ['message' => $exception->getMessage()]);
            }

            // 3) Se o OCR local trouxe pelo menos o cabecalho, devolve-o.
            if ($local !== null) {
                $local['aviso'] = trim('Linhas nao detetadas pelo OCR. '.$motivo.' '.implode(' ', $local['avisos'] ?? []));

                return response()->json($local);
            }

            return response()->json(['message' => $motivo], 422);
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
        ], $this->validationMessages());

        $ficheiroPath = null;
        $ficheiroIsImage = false;
        $itensIa = [];

        if ($request->hasFile('ficheiro')) {
            $file = $request->file('ficheiro');
            $ficheiroIsImage = str_starts_with($file->getMimeType() ?? '', 'image/');

            // Foto ou PDF sem linhas preenchidas a mao: tenta ler a fatura logo aqui.
            if ($this->podeLerComIa($file) && empty($data['items'])) {
                $itensIa = $this->lerItensComIa($file);
            }

            $ficheiroPath = $file->store('despesas', 'public');
        }

        if ($itensIa !== []) {
            $data['items'] = $itensIa;
        }

        $despesa = DB::transaction(function () use ($data, $ficheiroPath): Despesa {
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

            return $despesa;
        });

        $message = 'Entrada registada com sucesso.';

        if ($itensIa !== []) {
            $message = 'Entrada registada. A IA leu '.count($itensIa).' linha(s) da fatura - confirme os valores.';
        } elseif ($ficheiroPath && $ficheiroIsImage) {
            $this->queueAiJob($despesa, $ficheiroPath);
            $message = 'Entrada registada. Nao consegui ler a fatura na hora; a IA em casa vai tentar dentro de cerca de 1 minuto.';
        }

        return redirect()->route('despesas.index')->with('status', $message);
    }

    public function edit(Despesa $despesa): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $despesa->load('items', 'aiJobs');

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
        ], $this->validationMessages());

        $ficheiroPath = $despesa->ficheiro_path;
        $ficheiroIsNewImage = false;
        $itensIa = [];

        if ($request->hasFile('ficheiro')) {
            if ($ficheiroPath) {
                Storage::disk('public')->delete($ficheiroPath);
            }

            $file = $request->file('ficheiro');
            $ficheiroIsNewImage = str_starts_with($file->getMimeType() ?? '', 'image/');

            if ($this->podeLerComIa($file) && empty($data['items'])) {
                $itensIa = $this->lerItensComIa($file);
            }

            $ficheiroPath = $file->store('despesas', 'public');
        }

        if ($itensIa !== []) {
            $data['items'] = $itensIa;
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
                'categoria' => 'entrada_produtos',
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

        $message = 'Entrada atualizada com sucesso.';

        if ($itensIa !== []) {
            $message = 'Entrada atualizada. A IA leu '.count($itensIa).' linha(s) da fatura - confirme os valores.';
        } elseif ($ficheiroPath && $ficheiroIsNewImage) {
            $this->queueAiJob($despesa, $ficheiroPath);
            $message = 'Entrada atualizada. Nao consegui ler a fatura na hora; a IA em casa vai tentar dentro de cerca de 1 minuto.';
        }

        return redirect()->route('despesas.index')->with('status', $message);
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

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

    private function podeLerComIa(UploadedFile $file): bool
    {
        $mime = (string) ($file->getMimeType() ?? '');

        return str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || strtolower((string) $file->getClientOriginalExtension()) === 'pdf';
    }

    /**
     * Le as linhas da fatura com a IA. Nunca rebenta o guardar: se falhar
     * devolve um array vazio e a entrada e gravada na mesma.
     */
    private function lerItensComIa(UploadedFile $file): array
    {
        $local = $this->lerFaturaLocalmente($file);

        if ($local !== null && $local['items'] !== []) {
            return $local['items'];
        }

        if (! config('services.openai.auto_despesas', true)) {
            return [];
        }

        try {
            $dados = app(FaturaAiExtractor::class)->extract($file, 40);

            return $this->normalizarItensIa($dados['items'] ?? []);
        } catch (Throwable $exception) {
            Log::warning('Leitura automatica da fatura por IA falhou', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Leitura local da fatura (pdftotext / tesseract / zbarimg), como no
     * gestao.ateneya.com. Devolve null se o extractor rebentar.
     */
    private function lerFaturaLocalmente(UploadedFile $file): ?array
    {
        $caminho = null;

        try {
            $extensao = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
            $caminho = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'fatura_'.uniqid('', true).'.'.$extensao;
            copy($file->getRealPath(), $caminho);

            $lido = app(PaperInvoiceExtractor::class)->extract($caminho);

            return $this->mapearLeituraLocal($lido);
        } catch (Throwable $exception) {
            Log::warning('Leitura local da fatura falhou', ['message' => $exception->getMessage()]);

            return null;
        } finally {
            if ($caminho !== null && is_file($caminho)) {
                @unlink($caminho);
            }
        }
    }

    private function mapearLeituraLocal(array $lido): array
    {
        $items = [];

        foreach ($lido['products'] ?? [] as $produto) {
            $quantidade = (float) ($produto['quantity'] ?? 1);
            $quantidade = $quantidade > 0 ? $quantidade : 1;

            $preco = (float) ($produto['unitPrice'] ?? 0);
            $totalLinha = (float) ($produto['lineTotal'] ?? 0);

            if ($preco <= 0 && $totalLinha > 0) {
                $preco = $totalLinha / $quantidade;
            }

            $items[] = [
                'descricao' => (string) ($produto['description'] ?? ''),
                'quantidade' => $quantidade,
                'unidade_compra' => 'un',
                'unidades_por_quantidade' => 1,
                'quantidade_unidades' => $quantidade,
                'preco_unitario' => $preco,
                'iva_percentagem' => (float) ($produto['vatRate'] ?? 0),
                'notas' => '',
            ];
        }

        $numero = trim((string) ($lido['invoice']['number'] ?? ''));
        $nome = trim((string) ($lido['supplier']['name'] ?? ''));
        $nif = trim((string) ($lido['supplier']['taxNumber'] ?? ''));
        $total = (float) ($lido['invoice']['total'] ?? 0);

        return [
            'titulo' => $numero !== '' ? 'Fatura '.$numero : $nome,
            'numero_fatura' => $numero,
            'fornecedor' => $nome !== '' ? $nome : $nif,
            'data' => $this->dataParaInput((string) ($lido['invoice']['date'] ?? '')),
            'valor' => $total > 0 ? $total : null,
            'items' => $this->normalizarItensIa($items),
            'fonte' => 'ocr',
            'avisos' => array_values($lido['warnings'] ?? []),
        ];
    }

    private function dataParaInput(string $valor): ?string
    {
        $valor = trim($valor);

        if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $valor, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }

        return null;
    }

    private function normalizarItensIa(array $items): array
    {
        $normalizados = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $descricao = trim((string) ($item['descricao'] ?? ''));

            if ($descricao === '') {
                continue;
            }

            $quantidade = (float) ($item['quantidade'] ?? 1);
            $quantidade = $quantidade > 0 ? $quantidade : 1;

            $fator = (float) ($item['unidades_por_quantidade'] ?? 1);
            $fator = $fator > 0 ? $fator : 1;

            $unidades = (float) ($item['quantidade_unidades'] ?? 0);
            $unidades = $unidades > 0 ? $unidades : $quantidade * $fator;

            $iva = (float) ($item['iva_percentagem'] ?? 23);
            $iva = in_array($iva, self::TAXAS_IVA) ? $iva : 23;

            $unidade = trim((string) ($item['unidade_compra'] ?? 'un'));

            $normalizados[] = [
                'descricao' => mb_substr($descricao, 0, 255),
                'quantidade' => round($quantidade, 3),
                'unidade_compra' => $unidade !== '' ? mb_substr($unidade, 0, 20) : 'un',
                'unidades_por_quantidade' => round($fator, 3),
                'quantidade_unidades' => round($unidades, 3),
                'preco_unitario' => round(max(0, (float) ($item['preco_unitario'] ?? 0)), 4),
                'iva_percentagem' => $iva,
                'notas' => trim((string) ($item['notas'] ?? '')),
            ];
        }

        return $normalizados;
    }

    private function queueAiJob(Despesa $despesa, string $ficheiroPath): void
    {
        $despesa->aiJobs()
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        AiJob::create([
            'despesa_id' => $despesa->id,
            'status' => 'pending',
            'image_path' => $ficheiroPath,
        ]);
    }

    private function validationMessages(): array
    {
        return [
            'ficheiro.uploaded' => 'A foto nao conseguiu chegar ao servidor. Tente novamente com uma foto mais leve ou confirme upload_max_filesize, post_max_size e permissoes do temporario PHP.',
            'ficheiro.max' => 'A foto e demasiado grande. Tente novamente com uma foto mais leve.',
            'ficheiro.mimes' => 'O ficheiro deve ser JPG, PNG, GIF, WEBP ou PDF.',
        ];
    }
}
