<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NasApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('services.nas.ai_api_key');

        if (blank($key) || $request->header('X-API-Key') !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
