<?php

namespace App\Http\Controllers;

use App\Models\WooOrder;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('encomendas.index', [
            'q' => $q,
            'status' => $status,
            'diaEntrega' => $diaEntrega,
            'tipo' => $tipo,
            'sourceType' => $sourceType,
            'orders' => WooOrder::query()
                ->with('preparacaoItems')
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
                ->when($status === 'em_processamento', fn ($query) => $query
                    ->where('source_type', 'order')
                    ->whereIn('status', self::EM_PROCESSAMENTO_STATUSES)
                )
                ->when(in_array($diaEntrega, ['quarta', 'sabado'], true), fn ($query) => $query->where('dia_entrega', $diaEntrega))
                ->when($sourceType === 'order', fn ($query) => $query->whereIn('status', self::EM_PROCESSAMENTO_STATUSES))
                ->when($sourceType === 'subscription', fn ($query) => $query->where('source_type', 'subscription'))
                ->when($tipo === 'adiadas', fn ($query) => $query->whereNotNull('postponed_until'))
                ->when($tipo === 'preferencias', fn ($query) => $query->where(function ($query): void {
                    $query->whereNotNull('preferences_text')
                        ->orWhereJsonLength('excluded_products', '>', 0);
                }))
                ->latest('synced_at')
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
        $encomenda->load('preparacaoItems.feitoPor');

        return view('encomendas.show', compact('encomenda'));
    }

    public function updateProfile(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $data = $request->validate([
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

        $encomenda->update([
            'postponed_until' => $data['postponed_until'],
        ]);

        return back()->with('status', 'Encomenda adiada ate '.$encomenda->fresh()->postponed_until->format('d/m/Y').'.');
    }

    public function clearPostpone(WooOrder $encomenda): RedirectResponse
    {
        $encomenda->update([
            'postponed_until' => null,
        ]);

        return back()->with('status', 'Adiamento removido.');
    }

    public function duplicate(WooOrder $encomenda): RedirectResponse
    {
        $novaEncomenda = $encomenda->replicate([
            'woo_id',
            'ordered_at',
            'postponed_until',
            'next_payment_at',
            'first_delivery_at',
            'delivery_dates',
            'cancelled_delivery_dates',
            'subscription_ends_at',
            'scheduled_delivery_at',
            'raw_payload',
            'synced_at',
        ]);

        $novaEncomenda->forceFill([
            'woo_id' => $this->nextLocalWooId($encomenda),
            'ordered_at' => now(),
            'postponed_until' => null,
            'next_payment_at' => null,
            'first_delivery_at' => null,
            'delivery_dates' => [],
            'cancelled_delivery_dates' => [],
            'subscription_ends_at' => null,
            'scheduled_delivery_at' => null,
            'preferences_text' => $encomenda->preferences_text,
            'raw_payload' => [
                'duplicated_from' => $encomenda->woo_id,
                'duplicated_at' => now()->toIso8601String(),
            ],
            'synced_at' => now(),
        ])->save();

        return back()->with('status', "Encomenda duplicada para renovacao: #{$novaEncomenda->woo_id}.");
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

    private function nextLocalWooId(WooOrder $encomenda): int
    {
        $wooId = 900000000000 + $encomenda->id;

        while (WooOrder::where('woo_id', $wooId)->exists()) {
            $wooId++;
        }

        return $wooId;
    }
}
