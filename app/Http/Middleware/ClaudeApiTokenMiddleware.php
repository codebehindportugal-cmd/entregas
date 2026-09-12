<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O mesmo token dos endpoints /api/claude/* deste projecto, em Bearer.
 *
 * Nao se inventou aqui outra autenticacao: o CLAUDE_API_TOKEN ja existe, ja e o
 * que o chat usa para ler as subscricoes e os mapas mensais, e mais uma chave
 * era mais uma chave para perder. Este projecto nao tem Sanctum instalado, por
 * isso nao ha tokens por utilizador — se um dia houver, troca-se este middleware
 * por `auth:sanctum` e as rotas ficam iguais.
 *
 * E middleware e nao um metodo do controlador para o 401 vir antes da validacao
 * do corpo: quem nao tem token nao devia ficar a saber que campos faltavam.
 */
class ClaudeApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configurado = config('services.claude.api_token');

        if (blank($configurado)) {
            return response()->json([
                'sucesso' => false,
                'dados' => null,
                'avisos' => [],
                'erros' => ['token' => ['CLAUDE_API_TOKEN nao esta configurado no servidor.']],
            ], 503);
        }

        $token = $request->bearerToken() ?: $request->header('X-Claude-Api-Key');

        if (! is_string($token) || ! hash_equals((string) $configurado, $token)) {
            return response()->json([
                'sucesso' => false,
                'dados' => null,
                'avisos' => [],
                'erros' => ['token' => ['Token invalido.']],
            ], 401);
        }

        return $next($request);
    }
}
