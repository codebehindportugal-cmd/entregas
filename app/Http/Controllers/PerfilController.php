<?php

namespace App\Http\Controllers;

use App\Support\ChaveApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * O perfil de cada pessoa, com o botao da chave da API (29/09/2026).
 */
class PerfilController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('perfil.show', [
            'user' => $user,
            'podeTerChave' => ChaveApi::podeTer($user),
            'chave' => ChaveApi::actual($user),
            // So existe no pedido a seguir a gerar; a sessao apaga-a logo.
            'chaveNova' => session('chave_api_nova'),
        ]);
    }

    public function gerarChave(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Escreve a tua password.',
            'password.current_password' => 'A password nao esta certa.',
        ]);

        abort_unless(ChaveApi::podeTer($request->user()), 403, 'So um administrador pode ter chave da API.');

        ['token' => $token, 'revogados' => $revogados] = ChaveApi::emitir($request->user());

        return redirect()
            ->route('perfil.show')
            ->with('chave_api_nova', $token)
            ->with('status', $revogados > 0 ? 'Chave nova gerada. A anterior deixou de funcionar.' : 'Chave gerada.');
    }

    public function revogarChave(Request $request): RedirectResponse
    {
        ChaveApi::revogar($request->user());

        return redirect()->route('perfil.show')->with('status', 'Chave revogada.');
    }
}
