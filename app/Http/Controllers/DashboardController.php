<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\PreparacaoItem;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $hoje = now()->toDateString();
        $preparacaoHoje = PreparacaoItem::with(['corporate', 'wooOrder'])
            ->whereDate('data_preparacao', $hoje)
            ->latest()
            ->get();
        $entregasHojeQuery = RegistoEntrega::with(['corporate', 'user'])->whereDate('data_entrega', $hoje);
        $entregasHoje = (clone $entregasHojeQuery)->get();
        $b2cAtivas = WooOrder::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['processing', 'on-hold', 'pending'])
                    ->orWhere('status', 'subscricao');
            });

        return view('dashboard', [
            'corporatesAtivos' => Corporate::where('ativo', true)->count(),
            'colaboradoresAtivos' => User::where('role', 'colaborador')->where('ativo', true)->count(),
            'entregasHoje' => $entregasHoje->count(),
            'entreguesHoje' => $entregasHoje->where('status', 'entregue')->count(),
            'pendentesHoje' => $entregasHoje->where('status', 'pendente')->count(),
            'falhasHoje' => $entregasHoje->where('status', 'falhou')->count(),
            'progressoEntregas' => $entregasHoje->count() > 0 ? round(($entregasHoje->where('status', 'entregue')->count() / $entregasHoje->count()) * 100) : 0,
            'preparacaoTotal' => $preparacaoHoje->count(),
            'preparacaoFeita' => $preparacaoHoje->where('feito', true)->count(),
            'preparacaoPorFazer' => $preparacaoHoje->where('feito', false)->count(),
            'progressoPreparacao' => $preparacaoHoje->count() > 0 ? round(($preparacaoHoje->where('feito', true)->count() / $preparacaoHoje->count()) * 100) : 0,
            'b2cAtivas' => (clone $b2cAtivas)->count(),
            'subscricoesAtivas' => WooOrder::where('status', 'subscricao')->count(),
            'encomendasProcessamento' => WooOrder::whereIn('status', ['processing', 'on-hold', 'pending'])->count(),
            'ultimasEncomendas' => WooOrder::latest('synced_at')->limit(5)->get(),
            'proximasPreparacoes' => $preparacaoHoje->where('feito', false)->take(6),
            'entregasPendentes' => $entregasHoje->where('status', 'pendente')->take(6),
        ]);
    }
}
