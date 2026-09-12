<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'nas.api_key' => \App\Http\Middleware\NasApiKeyMiddleware::class,
            // Ingestao de faturas (/api/v1/faturas): o mesmo CLAUDE_API_TOKEN
            // dos endpoints /api/claude/*.
            'claude.api_token' => \App\Http\Middleware\ClaudeApiTokenMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (TokenMismatchException $e, Request $request) {
            return redirect()->route('login')
                ->with('status', 'A sessão expirou. Por favor, tente novamente.');
        });
    })->create();
