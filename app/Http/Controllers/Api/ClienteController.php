<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaCliente;
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
            // Tambem para clientes sem encomendas ainda (nota posta antes da primeira).
            'notas_cliente' => NotaCliente::textoDo($telefone),
        ], $perfil !== null && count($perfil['nomes']) > 1
            ? [['codigo' => 'CLIENTE_VARIOS_NOMES', 'mensagem' => 'Este telefone aparece com varios nomes: '.implode(', ', $perfil['nomes']).'.']]
            : []);
    }

    /**
     * Guardar as notas fixas de um cliente (pelo telefone). Substitui as que
     * havia; `notas` vazio apaga-as. Aparecem ao criar encomendas (backoffice
     * e /encomendas/validar) e na preparacao dos cabazes.
     */
    public function notas(Request $request): JsonResponse
    {
        $telefone = trim((string) $request->input('telefone'));
        $normalizado = ClientesB2c::normalizarTelefone($telefone);
        $erros = [];

        if ($normalizado === null) {
            $erros[] = ['codigo' => 'TELEFONE_INVALIDO', 'linha' => null, 'mensagem' => 'Manda telefone com pelo menos 9 digitos.', 'sugestoes' => []];
        }

        if (! $request->has('notas') || (! is_string($request->input('notas')) && $request->input('notas') !== null)) {
            $erros[] = ['codigo' => 'NOTAS_EM_FALTA', 'linha' => null, 'mensagem' => 'Manda notas (texto). Vazio ou null apaga as notas do cliente.', 'sugestoes' => []];
        } elseif (mb_strlen((string) $request->input('notas')) > 5000) {
            $erros[] = ['codigo' => 'NOTAS_DEMASIADO_LONGAS', 'linha' => null, 'mensagem' => 'As notas tem no maximo 5000 caracteres.', 'sugestoes' => []];
        }

        if ($erros !== []) {
            return $this->erro422($erros);
        }

        $notas = trim((string) $request->input('notas'));
        $modo = $request->input('modo') === 'acrescentar' ? 'acrescentar' : 'substituir';
        $existente = NotaCliente::where('telefone', $normalizado)->first();

        if ($modo === 'acrescentar' && $existente !== null && $notas !== '') {
            $notas = trim($existente->notas."\n".$notas);
        }

        if ($notas === '') {
            $existente?->delete();

            return $this->ok(['telefone_normalizado' => $normalizado, 'notas_cliente' => null, 'apagadas' => $existente !== null]);
        }

        $nome = trim((string) $request->input('nome')) ?: ($existente?->nome ?? app(ClientesB2c::class)->perfil($telefone)['nome'] ?? null);

        $nota = NotaCliente::updateOrCreate(['telefone' => $normalizado], ['notas' => $notas, 'nome' => $nome]);

        return $this->ok([
            'telefone_normalizado' => $normalizado,
            'nome' => $nota->nome,
            'notas_cliente' => $nota->notas,
            'cliente_tem_encomendas' => app(ClientesB2c::class)->encomendasDoTelefone($telefone)->isNotEmpty(),
        ]);
    }
}
