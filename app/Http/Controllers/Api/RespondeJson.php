<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * A forma das respostas da API de faturas.
 *
 * Igual a do agro.codebehind.pt e a da gestao.hortadamaria.com de proposito: sao
 * tres projectos diferentes mas o mesmo trabalho do outro lado (ler uma fatura de
 * papel e registá-la), e quem chama nao devia ter de aprender tres formatos.
 */
trait RespondeJson
{
    protected function ok(array $dados = [], array $avisos = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'sucesso' => true,
            'dados' => $dados,
            'avisos' => $avisos,
            'erros' => [],
        ], $status);
    }

    protected function criado(array $dados = [], array $avisos = []): JsonResponse
    {
        return $this->ok($dados, $avisos, 201);
    }

    protected function erro422(array $erros, array $avisos = []): JsonResponse
    {
        return response()->json([
            'sucesso' => false,
            'dados' => null,
            'avisos' => $avisos,
            'erros' => $erros,
        ], 422);
    }
}
