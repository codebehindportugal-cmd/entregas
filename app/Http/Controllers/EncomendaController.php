<?php

namespace App\Http\Controllers;

use App\Models\WooOrder;
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
                ->when($inicio, fn ($query) => $query->whereDate('ordered_at', '>=', $inicio))
                ->when($fim, fn ($query) => $query->whereDate('ordered_at', '<=', $fim))
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
            'source_type' => ['required', 'in:order,subscription'],
            'dia_entrega' => ['nullable', 'in:segunda,quarta,sabado'],
            'ciclo_entrega' => ['required', 'in:semanal,quinzenal'],
            'scheduled_delivery_at' => ['nullable', 'date'],
            'first_delivery_at' => ['nullable', 'date'],
            'next_payment_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'profile_preferences' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
        ]);

        $encomenda->update($data);

        return back()->with('status', 'Perfil do cliente atualizado.');
    }

    public function postpone(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $data = $request->validate([
            'postponed_until' => ['required', 'date'],
        ]);

        if ($encomenda->source_type === 'subscription' || in_array($encomenda->status, ['subscricao', 'wc-subscricao'], true)) {
            $encomenda->adiarProximaEntregaPara($data['postponed_until']);
        } else {
            $encomenda->update([
                'postponed_until' => $data['postponed_until'],
            ]);
        }

        return back()->with('status', 'Encomenda adiada ate '.$encomenda->fresh()->postponed_until->format('d/m/Y').'.');
    }

    public function clearPostpone(WooOrder $encomenda): RedirectResponse
    {
        $encomenda->update([
            'postponed_until' => null,
        ]);

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
        $message = "Encomenda publicada no WooCommerce em pagamento pendente: #{$novaEncomenda->woo_id}.";

        if ($paymentUrl) {
            $message .= " Link de pagamento: {$paymentUrl}";
        }

        return back()->with('status', $message);
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

        return redirect()->route('encomendas.index')->with('status', "Encomenda #{$encomenda->woo_id} marcada como concluida no WordPress.");
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

        return [$inicio, $fim];
    }
}
