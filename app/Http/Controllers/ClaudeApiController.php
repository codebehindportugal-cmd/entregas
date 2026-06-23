<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\RegistoEntrega;
use App\Models\WooOrder;
use App\Services\CorporateMonthlyMapService;
use App\Services\CorporateRelatorioService;
use App\Services\SimplePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClaudeApiController extends Controller
{
    public function subscricoes(Request $request): JsonResponse
    {
        $this->authorizeClaude($request);

        $validated = $request->validate([
            'dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'incluir_expiradas' => ['nullable', 'boolean'],
        ]);
        $dias = (int) ($validated['dias'] ?? 30);
        $incluirExpiradas = (bool) ($validated['incluir_expiradas'] ?? false);
        $hoje = today();
        $limite = $hoje->copy()->addDays($dias);

        $orders = WooOrder::query()
            ->where(function ($query): void {
                $query->where('source_type', 'subscription')
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active']);
            })
            ->with(['preparacaoItems', 'registoEntregas'])
            ->get()
            ->map(function (WooOrder $order) use ($hoje): array {
                $fim = $order->fimCicloSubscricao();
                $entregas = $order->entregasSubscricao();
                $proximaEncomenda = $order->proximaEncomendaSubscricao();

                return [
                    'id' => $order->id,
                    'woo_id' => $order->woo_id,
                    'cliente' => $order->billing_name,
                    'telefone' => $order->billing_phone,
                    'email' => $order->billing_email,
                    'estado' => $order->status,
                    'dia_entrega' => $order->dia_entrega,
                    'ciclo_entrega' => $order->ciclo_entrega,
                    'fim_subscricao' => $fim?->toDateString(),
                    'dias_para_terminar' => $fim !== null ? (int) $hoje->diffInDays($fim, false) : null,
                    'proxima_entrega' => $entregas['proxima'] ?? null,
                    'proxima_encomenda' => $proximaEncomenda?->toDateString(),
                    'entregas_total' => $entregas['total'] ?? 0,
                    'entregas_feitas' => $entregas['feitas'] ?? 0,
                    'entregas_por_realizar' => $entregas['por_realizar'] ?? 0,
                    'whatsapp_renovacao_url' => $order->whatsappRenovacaoUrl(),
                ];
            })
            ->filter(function (array $order) use ($hoje, $limite, $incluirExpiradas): bool {
                if ($order['fim_subscricao'] === null) {
                    return false;
                }

                $fim = Carbon::parse($order['fim_subscricao']);

                if (! $incluirExpiradas && $fim->lessThan($hoje)) {
                    return false;
                }

                return $fim->lessThanOrEqualTo($limite);
            })
            ->sortBy('fim_subscricao')
            ->values();

        return response()->json([
            'data_referencia' => $hoje->toDateString(),
            'dias' => $dias,
            'total' => $orders->count(),
            'subscricoes' => $orders,
        ]);
    }

    public function mapasMensais(Request $request): JsonResponse
    {
        $this->authorizeClaude($request);

        $validated = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'ativas' => ['nullable', 'boolean'],
        ]);
        $mes = $validated['mes'] ?? now()->format('Y-m');
        $ativas = (bool) ($validated['ativas'] ?? true);

        $corporates = Corporate::query()
            ->when($ativas, fn ($query) => $query->where('ativo', true))
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get()
            ->map(fn (Corporate $corporate): array => [
                'id' => $corporate->id,
                'empresa' => $corporate->empresa,
                'sucursal' => $corporate->sucursal,
                'morada_entrega' => $corporate->moradaParaEntrega(),
                'ativo' => (bool) $corporate->ativo,
                'mes' => $mes,
                'pdf_url' => route('api.claude.empresas.mapa-mensal.pdf', [
                    'corporate' => $corporate,
                    'mes' => $mes,
                ]),
            ])
            ->values();

        return response()->json([
            'mes' => $mes,
            'total' => $corporates->count(),
            'empresas' => $corporates,
        ]);
    }

    public function mapaMensalPdf(
        Request $request,
        Corporate $corporate,
        CorporateMonthlyMapService $mapService,
        SimplePdfService $pdfService,
    ): Response {
        $this->authorizeClaude($request);

        $validated = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
        ]);
        $mes = $validated['mes'] ?? now()->format('Y-m');
        $mapa = $mapService->build($corporate, $mes);
        $filename = Str::slug($corporate->empresa.' '.$corporate->sucursal.' mapa mensal '.$mes).'.pdf';

        return response($pdfService->monthlyCorporateMap($mapa), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function entregasCorporates(Request $request): JsonResponse
    {
        $this->authorizeClaude($request);

        $validated = $request->validate([
            'data_inicio'  => ['required', 'date'],
            'data_fim'     => ['required', 'date', 'after_or_equal:data_inicio'],
            'corporate_id' => ['nullable', 'integer', 'exists:corporates,id'],
        ]);

        $inicio = Carbon::parse($validated['data_inicio'])->startOfDay();
        $fim    = Carbon::parse($validated['data_fim'])->endOfDay();

        $entregas = RegistoEntrega::query()
            ->with('corporate')
            ->whereHas('corporate')
            ->whereBetween('data_entrega', [$inicio->toDateString(), $fim->toDateString()])
            ->when(isset($validated['corporate_id']), fn ($q) => $q->where('corporate_id', $validated['corporate_id']))
            ->orderBy('data_entrega')
            ->orderBy('corporate_id')
            ->get()
            ->map(fn (RegistoEntrega $r) => [
                'id'           => $r->id,
                'data_entrega' => $r->data_entrega->toDateString(),
                'hora_entrega' => $r->hora_entrega?->format('H:i'),
                'status'       => $r->status,
                'nota'         => $r->nota,
                'corporate_id' => $r->corporate_id,
                'empresa'      => $r->corporate?->empresa,
                'sucursal'     => $r->corporate?->sucursal,
            ]);

        return response()->json([
            'data_inicio' => $inicio->toDateString(),
            'data_fim'    => $fim->toDateString(),
            'total'       => $entregas->count(),
            'entregas'    => $entregas,
        ]);
    }

    public function relatorioMensalPdf(
        Request $request,
        Corporate $corporate,
        CorporateRelatorioService $relatorioService,
    ): JsonResponse {
        $this->authorizeClaude($request);

        $validated = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
        ]);

        $mes    = $validated['mes'];
        $inicio = Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay();
        $fim    = $inicio->copy()->endOfMonth();

        $corporate->load('configSnapshots');
        $linhas = $relatorioService->buildLinhas($corporate, $inicio, $fim);

        $totais = [
            'entregue'        => $linhas->where('estado', 'entregue')->count(),
            'falhou'          => $linhas->where('estado', 'falhou')->count(),
            'nao_entregamos'  => $linhas->where('estado', 'nao_entregamos')->count(),
            'entrega_parcial' => $linhas->where('estado', 'entrega_parcial')->count(),
            'sem_registo'     => $linhas->where('estado', 'sem_registo')->count(),
        ];

        $pdf = Pdf::loadView('pdf.relatorio-mensal', [
            'corporate' => $corporate,
            'mes'       => $mes,
            'inicio'    => $inicio,
            'linhas'    => $linhas,
            'totais'    => $totais,
        ]);

        $storagePath = "relatorios/relatorio-{$corporate->id}-{$mes}.pdf";
        Storage::disk('local')->put($storagePath, $pdf->output());

        $url = URL::temporarySignedRoute(
            'relatorio.download',
            now()->addHours(24),
            ['path' => $storagePath]
        );

        return response()->json([
            'corporate_id' => $corporate->id,
            'empresa'      => $corporate->empresa,
            'mes'          => $mes,
            'pdf_url'      => $url,
            'expira_em'    => now()->addHours(24)->toIso8601String(),
            'totais'       => $totais,
        ]);
    }

    private function authorizeClaude(Request $request): void
    {
        $configuredToken = config('services.claude.api_token');

        abort_if(blank($configuredToken), 503, 'CLAUDE_API_TOKEN nao esta configurado.');

        $token = $request->bearerToken() ?: $request->header('X-Claude-Api-Key');

        abort_unless(is_string($token) && hash_equals((string) $configuredToken, $token), 401, 'Token invalido.');
    }
}
