<?php

use App\Http\Controllers\AiJobController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EncomendaController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\PedidoRecebidoController;
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
    // Os perfis B2C repetem-se; o cliente identifica-se pelo telefone.
    Route::get('/clientes', [ClienteController::class, 'show'])->name('clientes.show');
    // Notas fixas do cliente (por telefone): aparecem ao criar encomendas e na preparacao.
    Route::put('/clientes/notas', [ClienteController::class, 'notas'])->name('clientes.notas');
    Route::post('/encomendas/validar', [EncomendaController::class, 'validar'])->name('encomendas.validar');
    Route::post('/encomendas', [EncomendaController::class, 'store'])->name('encomendas.store');

});

/*
|--------------------------------------------------------------------------
| Caixa de pedidos (email e WhatsApp)
|--------------------------------------------------------------------------
|
| O trabalho do fim do dia do Claude le os emails de encomenda, interpreta-os
| no formato do /encomendas/validar e deixa-os aqui. Nada daqui cria
| encomendas: so o botao Confirmar do backoffice o faz.
|
| Aceita o token proprio da caixa (Definicoes de pedidos), que so abre estas
| rotas, alem das chaves de sempre.
|
*/
Route::prefix('v1')->name('api.v1.')->middleware('pedidos.api_token')->group(function (): void {
    Route::get('/pedidos-recebidos/produtos', [EncomendaController::class, 'produtos'])->name('pedidos-recebidos.produtos');
    Route::get('/pedidos-recebidos/clientes', [ClienteController::class, 'show'])->name('pedidos-recebidos.clientes');
    Route::put('/pedidos-recebidos/clientes/notas', [ClienteController::class, 'notas'])->name('pedidos-recebidos.clientes.notas');
    Route::get('/pedidos-recebidos/definicoes', [PedidoRecebidoController::class, 'definicoes'])->name('pedidos-recebidos.definicoes');
    Route::get('/pedidos-recebidos', [PedidoRecebidoController::class, 'index'])->name('pedidos-recebidos.index');
    Route::post('/pedidos-recebidos', [PedidoRecebidoController::class, 'store'])->name('pedidos-recebidos.store');
    Route::post('/pedidos-recebidos/lote', [PedidoRecebidoController::class, 'lote'])->name('pedidos-recebidos.lote');
    Route::put('/pedidos-recebidos/{pedidoRecebido}/interpretacao', [PedidoRecebidoController::class, 'interpretacao'])->name('pedidos-recebidos.interpretacao');
});
