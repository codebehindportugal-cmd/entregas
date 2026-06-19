<?php

use App\Http\Controllers\ClaudeApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('claude')->name('api.claude.')->group(function (): void {
    Route::get('/subscricoes', [ClaudeApiController::class, 'subscricoes'])->name('subscricoes');
    Route::get('/empresas/mapas-mensais', [ClaudeApiController::class, 'mapasMensais'])->name('empresas.mapas-mensais');
    Route::get('/empresas/entregas', [ClaudeApiController::class, 'entregasCorporates'])->name('empresas.entregas');
    Route::get('/empresas/{corporate}/mapa-mensal.pdf', [ClaudeApiController::class, 'mapaMensalPdf'])->name('empresas.mapa-mensal.pdf');
    Route::post('/empresas/{corporate}/relatorio-mensal', [ClaudeApiController::class, 'relatorioMensalPdf'])->name('empresas.relatorio-mensal');
});
