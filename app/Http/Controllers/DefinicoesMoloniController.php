<?php

namespace App\Http\Controllers;

use App\Support\MoloniSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Definicoes do Moloni editaveis na aplicacao. O que for guardado aqui manda
 * sobre o .env; um campo deixado vazio volta ao valor do .env.
 * As credenciais ficam de fora de proposito (so no .env).
 */
class DefinicoesMoloniController extends Controller
{
    public function index(): View
    {
        $guardados = MoloniSettings::guardados();
        $esquema = MoloniSettings::esquema();

        $valores = [];
        $origens = [];

        foreach (MoloniSettings::chaves() as $chave) {
            $guardado = $guardados[$chave] ?? null;
            $efetivo = config('moloni.'.$chave);

            $valores[$chave] = $guardado ?? $efetivo;
            $origens[$chave] = filled($guardado) || $guardado === '0' || $guardado === 0 || $guardado === false
                ? 'app'
                : (filled($efetivo) || $efetivo === 0 || $efetivo === false ? 'env' : 'vazio');
        }

        return view('definicoes-moloni.index', [
            'esquema' => $esquema,
            'valores' => $valores,
            'origens' => $origens,
            'companyId' => config('moloni.company_id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $regras = [];

        foreach (MoloniSettings::esquema() as $grupo) {
            foreach ($grupo['campos'] as $chave => $campo) {
                $regras[$chave] = match ($campo['tipo']) {
                    'numero' => ['nullable', 'integer', 'min:0'],
                    'decimal' => ['nullable', 'numeric', 'min:0'],
                    'booleano' => ['nullable', 'boolean'],
                    'textarea' => ['nullable', 'string', 'max:2000'],
                    default => ['nullable', 'string', 'max:255'],
                };
            }
        }

        $data = $request->validate($regras);

        // Os booleanos vem so quando estao ligados: forca o valor nos dois casos.
        foreach (MoloniSettings::esquema() as $grupo) {
            foreach ($grupo['campos'] as $chave => $campo) {
                if ($campo['tipo'] === 'booleano') {
                    $data[$chave] = $request->boolean($chave) ? '1' : '0';
                }
            }
        }

        MoloniSettings::guardar($data);

        return redirect()->route('definicoes-moloni.index')->with('status', 'Definicoes do Moloni guardadas.');
    }
}
