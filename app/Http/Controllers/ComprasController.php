<?php

namespace App\Http\Controllers;

use App\Models\CompraPrecoMapping;
use App\Services\ComprasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ComprasController extends Controller
{
    public function __invoke(Request $request, ComprasService $compras): View
    {
        $periodo = $request->string('periodo')->toString() ?: 'semana';
        $inicio = filled($request->input('inicio'))
            ? Carbon::parse($request->input('inicio'))
            : now();

        if ($periodo === 'dia') {
            $inicio = $inicio->copy()->startOfDay();
            $fim = $inicio->copy();
        } elseif ($periodo === 'mes') {
            $inicio = $inicio->copy()->startOfMonth();
            $fim = $inicio->copy()->endOfMonth();
        } elseif ($periodo === 'personalizado') {
            $fim = filled($request->input('fim'))
                ? Carbon::parse($request->input('fim'))
                : $inicio->copy()->addDays(6);
        } else {
            $inicio = $inicio->copy()->startOfWeek();
            $fim = $inicio->copy()->endOfWeek();
        }

        if ($fim->lt($inicio)) {
            $fim = $inicio->copy();
        }

        if ($inicio->diffInDays($fim) > 31) {
            $fim = $inicio->copy()->addDays(31);
        }

        $calculo = $compras->calcular($inicio, $fim, $request->input('pesos', []));

        return view('compras.index', [
            'periodo' => $periodo,
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'labels' => ComprasService::FRUTAS,
            ...$calculo,
        ]);
    }

    public function updatePrecos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'precos' => ['nullable', 'array'],
            'precos.*' => ['nullable', 'exists:tabela_preco_itens,id'],
        ]);

        foreach (ComprasService::FRUTAS as $produto => $label) {
            $itemId = $data['precos'][$produto] ?? null;

            if (blank($itemId)) {
                CompraPrecoMapping::where('produto', $produto)->delete();
                continue;
            }

            CompraPrecoMapping::updateOrCreate(
                ['produto' => $produto],
                ['tabela_preco_item_id' => $itemId],
            );
        }

        return back()->with('status', 'Associacoes de precos atualizadas.');
    }
}
