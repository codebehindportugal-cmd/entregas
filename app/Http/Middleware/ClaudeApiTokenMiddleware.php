<?php

namespace App\Http\Middleware;

use App\Support\ChaveApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * O mesmo token dos endpoints /api/claude/* deste projecto, em Bearer.
 *
 * Nao se inventou aqui outra autenticacao: o CLAUDE_API_TOKEN ja existe, ja e o
 * que o chat usa para ler as subscricoes e os mapas mensais, e mais uma chave
 * era mais uma chave para perder. Este projecto nao tem Sanctum instalado; desde
 * 29/09/2026 ha chaves por utilizador numa tabela propria (App\Support\ChaveApi),
 * geradas no perfil, que valem ao lado do CLAUDE_API_TOKEN.
 *
 * E middleware e nao um metodo do controlador para o 401 vir antes da validacao
 * do corpo: quem nao tem token nao devia ficar a saber que campos faltavam.
 */
class ClaudeApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 29/09/2026: alem do CLAUDE_API_TOKEN, aceita as chaves que cada
        // administrador gera no perfil (App\Support\ChaveApi).
        if (! ChaveApi::haAlgumaConfigurada()) {
            return response()->json([
                'sucesso' => false,
                'dados' => null,
                'avisos' => [],
                'erros' => ['token' => ['Nenhuma chave da API configurada: gera uma no perfil ou define CLAUDE_API_TOKEN.']],
            ], 503);
        }

        $token = $request->bearerToken() ?: $request->header('X-Claude-Api-Key');

        if (! ChaveApi::valida(is_string($token) ? $token : null)) {
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
