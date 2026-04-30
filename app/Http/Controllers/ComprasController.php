<?php

namespace App\Http\Controllers;

use App\Services\ComprasService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ComprasController extends Controller
{
    public function __invoke(Request $request, ComprasService $compras): View
    {
        $inicio = filled($request->input('inicio'))
            ? Carbon::parse($request->input('inicio'))
            : now();

        $fim = filled($request->input('fim'))
            ? Carbon::parse($request->input('fim'))
            : $inicio->copy()->addDays(6);

        if ($fim->lt($inicio)) {
            $fim = $inicio->copy();
        }

        if ($inicio->diffInDays($fim) > 31) {
            $fim = $inicio->copy()->addDays(31);
        }

        $calculo = $compras->calcular($inicio, $fim, $request->input('pesos', []));

        return view('compras.index', [
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'labels' => ComprasService::FRUTAS,
            ...$calculo,
        ]);
    }
}
