<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClientesB2c;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Procurar um cliente B2C pelo telefone, antes de validar a encomenda.
 *
 * Os perfis repetem-se (cada encomenda do site e um), por isso a pesquisa e
 * pelo telefone e devolve um so cliente com todas as encomendas dele.
 */
class ClienteController extends Controller
{
    use RespondeJson;

    public function show(Request $request, ClientesB2c $clientes): JsonResponse
    {
        $telefone = trim((string) $request->string('telefone'));
        $normalizado = ClientesB2c::normalizarTelefone($telefone);

        if ($normalizado === null) {
            return $this->erro422([[
                'codigo' => 'TELEFONE_INVALIDO',
                'linha' => null,
                'mensagem' => 'Manda ?telefone= com pelo menos 9 digitos.',
                'sugestoes' => [],
            ]]);
        }

        $perfil = $clientes->perfil($telefone);

        return $this->ok([
            'encontrado' => $perfil !== null,
            'telefone_normalizado' => $normalizado,
            'cliente' => $perfil,
        ], $perfil !== null && count($perfil['nomes']) > 1
            ? [['codigo' => 'CLIENTE_VARIOS_NOMES', 'mensagem' => 'Este telefone aparece com varios nomes: '.implode(', ', $perfil['nomes']).'.']]
            : []);
    }
}
