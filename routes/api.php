<?php

use App\Http\Controllers\AiJobController;
use App\Http\Controllers\Api\EncomendaController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\ClaudeApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai')->name('api.ai.')->middleware('nas.api_key')->group(function (): void {
    Route::get('/pending-jobs', [AiJobController::class, 'pendingJobs'])->name('pending-jobs');
    Route::post('/job-result', [AiJobController::class, 'jobResult'])->name('job-result');
});

Route::prefix('claude')->name('api.claude.')->group(function (): void {
    Route::get('/subscricoes', [ClaudeApiController::class, 'subscricoes'])->name('subscricoes');
    Route::get('/empresas/mapas-mensais', [ClaudeApiController::class, 'mapasMensais'])->name('empresas.mapas-mensais');
    Route::get('/empresas/entregas', [ClaudeApiController::class, 'entregasCorporates'])->name('empresas.entregas');
    Route::get('/empresas/{corporate}/mapa-mensal.pdf', [ClaudeApiController::class, 'mapaMensalPdf'])->name('empresas.mapa-mensal.pdf');
    Route::post('/empresas/{corporate}/relatorio-mensal', [ClaudeApiController::class, 'relatorioMensalPdf'])->name('empresas.relatorio-mensal');
});

/*
|--------------------------------------------------------------------------
| Faturas de compra (ingestao)
|--------------------------------------------------------------------------
|
| As faturas de papel lidas no chat entram por aqui como entradas de produtos
| (Despesa + FaturaItem), o mesmo que o ecra de despesas cria. A leitura por IA
| do ecra (extrairIa / AiJob) fica como esta — sao duas vias para o mesmo sitio.
|
| Mesmo caminho e mesma forma de resposta do agro.codebehind.pt e do
| gestao.ateneya.com. Autenticacao: o CLAUDE_API_TOKEN que os endpoints
| /api/claude/* deste projecto ja usam, em Bearer (ClaudeApiTokenMiddleware).
|
*/
Route::prefix('v1')->name('api.v1.')->middleware('claude.api_token')->group(function (): void {
    Route::post('/faturas', [FaturaController::class, 'store'])->name('faturas.store');
    Route::post('/faturas/lote', [FaturaController::class, 'lote'])->name('faturas.lote');
    Route::post('/faturas/{despesa}/ficheiro', [FaturaController::class, 'ficheiro'])->name('faturas.ficheiro');

    /*
    |----------------------------------------------------------------------
    | Encomendas ditadas por WhatsApp
    |----------------------------------------------------------------------
    |
    | O cliente manda a encomenda por mensagem, eu colo o texto no chat e o
    | chat cria-a aqui. Sao dois passos: /validar interpreta e converte as
    | quantidades (o Woo so aceita linhas inteiras, "2 kg de ameixa" tem de
    | virar 4 x embalagem de 500 g) e devolve um token; so com esse token e
    | que /encomendas cria mesmo a encomenda e devolve o link de pagamento.
    |
    */
    Route::get('/produtos', [EncomendaController::class, 'produtos'])->name('produtos.index');
    Route::post('/encomendas/validar', [EncomendaController::class, 'validar'])->name('encomendas.validar');
    Route::post('/encomendas', [EncomendaController::class, 'store'])->name('encomendas.store');
});
