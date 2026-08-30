<?php

namespace App\Http\Controllers;

use App\Models\Viatura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestao das viaturas. A matricula escolhida aqui e a que a preparacao poe na
 * guia de transporte do Moloni.
 */
class ViaturaController extends Controller
{
    public function index(): View
    {
        return view('viaturas.index', [
            'viaturas' => Viatura::query()
                ->orderByDesc('ativo')
                ->orderBy('ordem')
                ->orderBy('matricula')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);

        Viatura::create($data);

        return redirect()->route('viaturas.index')->with('status', 'Viatura adicionada.');
    }

    public function update(Request $request, Viatura $viatura): RedirectResponse
    {
        $viatura->update($this->validar($request, $viatura));

        return redirect()->route('viaturas.index')->with('status', 'Viatura guardada.');
    }

    public function destroy(Viatura $viatura): RedirectResponse
    {
        $viatura->delete();

        return redirect()->route('viaturas.index')->with('status', 'Viatura removida.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Viatura $viatura = null): array
    {
        $data = $request->validate([
            'matricula' => [
                'required', 'string', 'max:20',
                'unique:viaturas,matricula'.($viatura !== null ? ','.$viatura->id : ''),
            ],
            'nome' => ['nullable', 'string', 'max:255'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'matricula.unique' => 'Ja existe uma viatura com essa matricula.',
        ]);

        return [
            'matricula' => mb_strtoupper(trim($data['matricula'])),
            'nome' => filled($data['nome'] ?? null) ? trim($data['nome']) : null,
            'ativo' => $request->boolean('ativo'),
            'ordem' => (int) ($data['ordem'] ?? 0),
        ];
    }
}
