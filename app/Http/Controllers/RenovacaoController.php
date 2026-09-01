<?php

namespace App\Http\Controllers;

use App\Models\WooOrder;
use App\Services\RenovacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class RenovacaoController extends Controller
{
    public function index(): View
    {
        $subscricoes = WooOrder::query()
            ->where(function ($query): void {
                $query->where('source_type', 'subscription')
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active']);
            })
            ->whereNotIn('status', ['completed', 'wc-completed', 'cancelled', 'wc-cancelled'])
            ->orderBy('billing_name')
            ->get()
            ->filter(fn (WooOrder $order): bool => $order->cicloTerminado())
            ->values();

        // Por enviar primeiro: sao as que estao a espera de alguem.
        $porEnviar = $subscricoes->filter(
            fn (WooOrder $order): bool => $order->renovada_em !== null && $order->renovacao_enviada_em === null
        )->values();

        $porRenovar = $subscricoes->filter(
            fn (WooOrder $order): bool => $order->renovada_em === null
        )->values();

        $enviadas = $subscricoes->filter(
            fn (WooOrder $order): bool => $order->renovacao_enviada_em !== null
        )->values();

        return view('renovacoes.index', compact('porEnviar', 'porRenovar', 'enviadas'));
    }

    public function store(WooOrder $encomenda, RenovacaoService $renovacao): RedirectResponse
    {
        try {
            $nova = $renovacao->renovar($encomenda);
        } catch (Throwable $exception) {
            return back()->withErrors(['renovacao' => $exception->getMessage()]);
        }

        return back()->with('status', "Renovacao criada no WooCommerce em pagamento pendente: #{$nova->woo_id}.");
    }

    public function marcarEnviada(WooOrder $encomenda): RedirectResponse
    {
        $encomenda->marcarRenovacaoEnviada();

        return back()->with('status', 'Renovacao marcada como enviada.');
    }
}
