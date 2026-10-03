<?php

namespace App\Http\Controllers;

use App\Support\PedidosSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Definicoes da caixa de pedidos: etiquetas do Gmail e chaves da WhatsApp Cloud
 * API, editaveis no site. Os segredos ficam encriptados e nunca sao mostrados.
 */
class DefinicoesPedidosController extends Controller
{
    public function index(): View
    {
        $valores = [];
        $definidos = [];

        foreach (PedidosSettings::campos() as $chave => $campo) {
            if ($campo['tipo'] === 'segredo') {
                $definidos[$chave] = PedidosSettings::definido($chave);
            } else {
                $valores[$chave] = PedidosSettings::get($chave);
            }
        }

        return view('definicoes-pedidos.index', [
            'esquema' => PedidosSettings::esquema(),
            'valores' => $valores,
            'definidos' => $definidos,
            'webhookUrl' => route('webhooks.whatsapp.receber'),
            'tokenCriadoEm' => PedidosSettings::tokenCriadoEm(),
            'tokenNovo' => session('token_novo'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $regras = ['apagar' => ['nullable', 'array'], 'apagar.*' => ['string']];

        foreach (PedidosSettings::campos() as $chave => $campo) {
            $regras[$chave] = match ($campo['tipo']) {
                'booleano' => ['nullable', 'boolean'],
                'segredo' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $data = $request->validate($regras);

        foreach (PedidosSettings::campos() as $chave => $campo) {
            if ($campo['tipo'] === 'booleano') {
                $data[$chave] = $request->boolean($chave) ? '1' : '0';
            }
        }

        PedidosSettings::guardar($data, $data['apagar'] ?? []);

        return redirect()->route('definicoes-pedidos.index')->with('status', 'Definicoes de pedidos guardadas.');
    }

    public function gerarToken(): RedirectResponse
    {
        return redirect()->route('definicoes-pedidos.index')
            ->with('token_novo', PedidosSettings::gerarToken())
            ->with('status', 'Token da caixa de pedidos gerado. Copia-o agora: nao volta a aparecer.');
    }

    public function revogarToken(): RedirectResponse
    {
        PedidosSettings::revogarToken();

        return redirect()->route('definicoes-pedidos.index')->with('status', 'Token da caixa de pedidos apagado.');
    }
}
