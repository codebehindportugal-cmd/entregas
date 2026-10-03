<?php

namespace App\Http\Middleware;

use App\Support\ChaveApi;
use App\Support\PedidosSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas que o trabalho do fim do dia (caixa de pedidos) usa.
 *
 * Aceita as chaves de sempre (CLAUDE_API_TOKEN / chaves do perfil) e tambem o
 * token proprio da caixa de pedidos, gerado em Definicoes de pedidos. Esse token
 * so passa aqui: ler o catalogo e os clientes e deixar pedidos na caixa. Nao
 * cria encomendas nem faturas, porque essas rotas so aceitam as chaves de sempre.
 */
class PedidosApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Claude-Api-Key');
        $token = is_string($token) ? $token : null;

        if (PedidosSettings::tokenValido($token) || ChaveApi::valida($token)) {
            return $next($request);
        }

        return response()->json([
            'sucesso' => false,
            'dados' => null,
            'avisos' => [],
            'erros' => ['token' => ['Token invalido.']],
        ], 401);
    }
}
