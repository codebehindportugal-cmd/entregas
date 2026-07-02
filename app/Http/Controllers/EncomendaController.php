<?php

namespace App\Http\Controllers;

use App\Models\WooOrder;
use App\Services\MoloniService;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class EncomendaController extends Controller
{
    private const EM_PROCESSAMENTO_STATUSES = ['processing', 'on-hold', 'pending'];

    private const STATUSES_EXCLUIDOS = ['completed', 'wc-completed'];

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $diaEntrega = $request->string('dia_entrega')->toString();
        $tipo = $request->string('tipo')->toString();
        $sourceType = $request->string('source_type')->toString();
        $periodo = $request->string('periodo')->toString();
        $inicio = filled($request->input('inicio'))
            ? Carbon::parse($request->input('inicio'))->startOfDay()
            : null;
        $fim = filled($request->input('fim'))
            ? Carbon::parse($request->input('fim'))->endOfDay()
            : null;
        [$inicio, $fim] = $this->periodRange($periodo, $inicio, $fim);
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'id' => 'woo_id',
            'cliente' => 'billing_name',
            'total' => 'total',
            'estado' => 'status',
            'tipo' => 'source_type',
            'sincronizado' => 'synced_at',
            'encomendado' => 'ordered_at',
            'entrega' => 'scheduled_delivery_at',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'synced_at';

        return view('encomendas.index', [
            'q' => $q,
            'status' => $status,
            'diaEntrega' => $diaEntrega,
            'tipo' => $tipo,
            'sourceType' => $sourceType,
            'periodo' => $periodo ?: '',
            'inicio' => $inicio?->toDateString(),
            'fim' => $fim?->toDateString(),
            'sort' => $sort ?: 'sincronizado',
            'direction' => $direction,
            'orders' => WooOrder::query()
                ->with(['preparacaoItems', 'registoEntregas'])
                ->whereNotIn('status', self::STATUSES_EXCLUIDOS)
                ->where(function ($query): void {
                    $query->whereIn('status', self::EM_PROCESSAMENTO_STATUSES)
                        ->orWhere('status', 'subscricao')
                        ->orWhere('source_type', 'subscription');
                })
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('woo_id', 'like', "%{$q}%")
                        ->orWhere('billing_name', 'like', "%{$q}%")
                        ->orWhere('billing_phone', 'like', "%{$q}%")
                        ->orWhere('billing_email', 'like', "%{$q}%");
                }))
                ->when($inicio || $fim, fn ($query) => $this->filterByDeliveryDate($query, $inicio, $fim))
                ->when($status === 'em_processamento', fn ($query) => $query
                    ->where('source_type', 'order')
                    ->whereIn('status', self::EM_PROCESSAMENTO_STATUSES)
                )
                ->when(in_array($diaEntrega, ['segunda', 'quarta', 'sabado'], true), fn ($query) => $query->where('dia_entrega', $diaEntrega))
                ->when($sourceType === 'order', fn ($query) => $query->whereIn('status', self::EM_PROCESSAMENTO_STATUSES))
                ->when($sourceType === 'subscription', fn ($query) => $query->where('source_type', 'subscription'))
                ->when($tipo === 'adiadas', fn ($query) => $query->whereNotNull('postponed_until'))
                ->when($tipo === 'preferencias', fn ($query) => $query->where(function ($query): void {
                    $query->whereNotNull('preferences_text')
                        ->orWhereJsonLength('excluded_products', '>', 0);
                }))
                ->orderBy($sortColumn, $direction)
                ->orderByDesc('synced_at')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => collect(['em_processamento']),
        ]);
    }

    public function sync(WooCommerceService $service): RedirectResponse
    {
        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        return back()->with('status', "WooCommerce sincronizado: {$result['fetched']} lidas ({$result['orders']} encomendas, {$result['subscriptions']} subscricoes), {$result['created']} criadas, {$result['updated']} atualizadas.");
    }

    public function show(WooOrder $encomenda): View
    {
        $encomenda->load(['preparacaoItems.feitoPor', 'registoEntregas.user']);

        return view('encomendas.show', compact('encomenda'));
    }

    public function updateProfile(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $data = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'customer_language' => ['nullable', 'in:pt,en'],
            'source_type' => ['required', 'in:order,subscription'],
            'dia_entrega' => ['nullable', 'in:segunda,quarta,sabado'],
            'ciclo_entrega' => ['required', 'in:semanal,quinzenal'],
            'scheduled_delivery_at' => ['nullable', 'date'],
            'first_delivery_at' => ['nullable', 'date'],
            'next_payment_at' => ['nullable', 'date', 'after_or_equal:first_delivery_at'],
            'subscription_ends_at' => ['nullable', 'date', 'after_or_equal:first_delivery_at'],
            'profile_preferences' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
        ]);

        $encomenda->update($data);

        if (
            $encomenda->isSubscricao()
            && $encomenda->wasChanged(['first_delivery_at', 'subscription_ends_at', 'ciclo_entrega', 'dia_entrega'])
        ) {
            $encomenda->forceFill(['delivery_dates' => []])->save();
        }

        return back()->with('status', 'Perfil do cliente atualizado.');
    }

    public function postpone(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $data = $request->validate([
            'postponed_until' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
        ]);

        if ($encomenda->source_type === 'subscription' || in_array($encomenda->status, ['subscricao', 'wc-subscricao'], true)) {
            if (filled($data['delivery_date'] ?? null)) {
                $encomenda->adiarEntregaDaSubscricaoPara($data['delivery_date'], $data['postponed_until']);
            } else {
                $encomenda->adiarProximaEntregaPara($data['postponed_until']);
            }
        } else {
            $encomenda->adiarEncomendaNormalPara($data['postponed_until']);
        }

        return back()->with('status', 'Encomenda adiada ate '.$encomenda->fresh()->postponed_until->format('d/m/Y').'.');
    }

    public function clearPostpone(WooOrder $encomenda): RedirectResponse
    {
        if ($encomenda->source_type === 'subscription' || in_array($encomenda->status, ['subscricao', 'wc-subscricao'], true)) {
            $encomenda->update([
                'postponed_until' => null,
            ]);
        } else {
            $encomenda->removerAdiamentoEncomendaNormal();
        }

        return back()->with('status', 'Adiamento removido.');
    }

    public function duplicate(WooOrder $encomenda, WooCommerceService $service): RedirectResponse
    {
        try {
            $result = $service->createPendingOrderFrom($encomenda);
        } catch (Throwable $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        $novaEncomenda = $result['order'];
        $paymentUrl = $result['payment_url'];
        $message = "Renovacao criada no WooCommerce em pagamento pendente: #{$novaEncomenda->woo_id}.";

        if ($paymentUrl) {
            $message .= " Link de pagamento: {$paymentUrl}";
        }

        return redirect()
            ->route('encomendas.show', $novaEncomenda)
            ->with('status', $message);
    }

    public function complete(WooOrder $encomenda, WooCommerceService $service): RedirectResponse
    {
        $encomenda->load('registoEntregas');

        if (! $encomenda->podeConcluirNoWordPress()) {
            return back()->withErrors(['complete' => 'Esta encomenda ainda nao tem todas as entregas marcadas como entregues.']);
        }

        try {
            $service->markAsCompleted($encomenda);
        } catch (Throwable $exception) {
            return back()->withErrors(['complete' => $exception->getMessage()]);
        }

        return redirect()->route('encomendas.index')->with('status', "Encomenda #{$encomenda->woo_id} fechada no WordPress.");
    }

    public function invoice(WooOrder $encomenda): RedirectResponse
    {
        $url = $encomenda->publicInvoiceUrl();

        if ($url === null) {
            return back()->withErrors(['invoice' => 'A fatura ainda nao existe. Para gerar sem login no WordPress e necessario criar um endpoint proprio no WordPress ou emitir diretamente pela API Moloni.']);
        }

        return redirect()->away($url);
    }

    public function publicInvoice(WooOrder $encomenda, MoloniService $moloni): RedirectResponse|View
    {
        $documentId = $encomenda->moloniDocumentId();

        abort_if($documentId === null, 404);

        $pdfUrl = $moloni->pdfUrl($documentId);

        if ($pdfUrl !== null) {
            return redirect()->away($pdfUrl);
        }

        return view('encomendas.public-invoice', compact('encomenda'));
    }

    public function destroy(WooOrder $encomenda): RedirectResponse
    {
        $encomenda->delete();

        return back()->with('status', 'Encomenda removida da lista local.');
    }

    public function destroyAll(): RedirectResponse
    {
        $count = WooOrder::count();

        DB::transaction(function (): void {
            WooOrder::query()->delete();
        });

        return redirect()->route('encomendas.index')->with('status', "{$count} encomendas removidas da cache local. Pode sincronizar novamente.");
    }

    private function periodRange(string $periodo, ?Carbon $inicio, ?Carbon $fim): array
    {
        if ($periodo === 'dia') {
            $date = $inicio ?: now()->startOfDay();

            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        if ($periodo === 'semana') {
            $date = $inicio ?: now();

            return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
        }

        if ($periodo === 'mes') {
            $date = $inicio ?: now();

            return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
        }

        if ($periodo === 'personalizado' && $inicio !== null && $fim === null) {
            return [$inicio->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }

        return [$inicio, $fim];
    }

    private function filterByDeliveryDate($query, ?Carbon $inicio, ?Carbon $fim): void
    {
        $dates = $this->datesForJsonDeliveryFilter($inicio, $fim);

        $query->where(function ($query) use ($inicio, $fim, $dates): void {
            $query->where(function ($query) use ($inicio, $fim): void {
                $this->whereDateBetween($query, 'postponed_until', $inicio, $fim);
            })->orWhere(function ($query) use ($inicio, $fim): void {
                $query->whereNull('postponed_until');
                $this->whereDateBetween($query, 'scheduled_delivery_at', $inicio, $fim);
            });

            if ($dates !== []) {
                $query->orWhere(function ($query) use ($dates): void {
                    foreach ($dates as $date) {
                        $query->orWhereJsonContains('delivery_dates', $date);
                    }
                });
            }

            $query->orWhere(function ($query) use ($inicio, $fim): void {
                $query->where(function ($query): void {
                    $query->whereNull('delivery_dates')
                        ->orWhereJsonLength('delivery_dates', 0);
                });
                $this->whereDateBetween($query, 'first_delivery_at', $inicio, $fim);
            });
        });
    }

    private function whereDateBetween($query, string $column, ?Carbon $inicio, ?Carbon $fim): void
    {
        if ($inicio !== null) {
            $query->whereDate($column, '>=', $inicio->toDateString());
        }

        if ($fim !== null) {
            $query->whereDate($column, '<=', $fim->toDateString());
        }
    }

    private function datesForJsonDeliveryFilter(?Carbon $inicio, ?Carbon $fim): array
    {
        if ($inicio === null || $fim === null) {
            return [];
        }

        $dates = [];
        $date = $inicio->copy()->startOfDay();
        $last = $fim->copy()->startOfDay();

        while ($date->lessThanOrEqualTo($last) && count($dates) < 120) {
            $dates[] = $date->toDateString();
            $date->addDay();
        }

        return $dates;
    }
}
