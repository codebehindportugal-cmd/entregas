<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()
                ->route('minhas-entregas.index')
                ->withErrors(['access' => 'A sua conta nao tem permissao para abrir essa area.']);
        }

        return $next($request);
    }
}
