<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComprasController;
use App\Http\Controllers\CorporateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EquipaController;
use App\Http\Controllers\ListaCabazController;
use App\Http\Controllers\TabelaPrecoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'create'])->name('login');
    Route::get('/login', [AuthController::class, 'create']);
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/minhas-entregas', [EntregaController::class, 'minhasEntregas'])->name('minhas-entregas.index');
    Route::get('/minhas-entregas/{registoEntrega}', [EntregaController::class, 'show'])->name('minhas-entregas.show');
    Route::put('/minhas-entregas/{registoEntrega}', [EntregaController::class, 'update'])->name('minhas-entregas.update');

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/corporates/exportar/json', [CorporateController::class, 'export'])->name('corporates.export');
        Route::post('/corporates/importar/json', [CorporateController::class, 'import'])->name('corporates.import');
        Route::resource('/corporates', CorporateController::class);
        Route::post('/corporates/{corporate}/historico', [CorporateController::class, 'storeHistorico'])->name('corporates.historico.store');
        Route::delete('/corporates/{corporate}/historico/{historico}', [CorporateController::class, 'destroyHistorico'])->name('corporates.historico.destroy');
        Route::resource('/equipa', EquipaController::class)->except(['show']);
        Route::get('/entregas', [EntregaController::class, 'index'])->name('entregas.index');
        Route::get('/entregas/verificacao', [EntregaController::class, 'verificacao'])->name('entregas.verificacao');
        Route::get('/preparacao', [EntregaController::class, 'preparacao'])->name('preparacao.index');
        Route::put('/preparacao/{item}', [EntregaController::class, 'updatePreparacaoItem'])->name('preparacao.update');
        Route::resource('/lista-cabazes', ListaCabazController::class)
            ->parameters(['lista-cabazes' => 'listaCabaz'])
            ->except(['show']);
        Route::post('/lista-cabazes/{listaCabaz}/itens', [ListaCabazController::class, 'storeItem'])->name('lista-cabazes.itens.store');
        Route::put('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'updateItem'])->name('lista-cabazes.itens.update');
        Route::delete('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'destroyItem'])->name('lista-cabazes.itens.destroy');
        Route::get('/lista-cabazes/{listaCabaz}/totais', [ListaCabazController::class, 'totais'])->name('lista-cabazes.totais');
        Route::get('/compras', ComprasController::class)->name('compras.index');
        Route::post('/compras/precos', [ComprasController::class, 'updatePrecos'])->name('compras.precos.update');
        Route::resource('/tabelas-precos', TabelaPrecoController::class)
            ->parameters(['tabelas-precos' => 'tabelaPreco']);
        Route::post('/tabelas-precos/manual', [TabelaPrecoController::class, 'manual'])->name('tabelas-precos.manual');
        Route::post('/tabelas-precos/{tabelaPreco}/itens', [TabelaPrecoController::class, 'storeItem'])->name('tabelas-precos.itens.store');
        Route::put('/tabelas-precos/itens/{item}', [TabelaPrecoController::class, 'updateItem'])->name('tabelas-precos.itens.update');
        Route::delete('/tabelas-precos/itens/{item}', [TabelaPrecoController::class, 'destroyItem'])->name('tabelas-precos.itens.destroy');
        Route::post('/tabelas-precos/{tabelaPreco}/clonar', [TabelaPrecoController::class, 'clonar'])->name('tabelas-precos.clonar');
        Route::get('/encomendas', [EncomendaController::class, 'index'])->name('encomendas.index');
        Route::post('/encomendas/sync', [EncomendaController::class, 'sync'])->name('encomendas.sync');
        Route::delete('/encomendas/limpar-todas', [EncomendaController::class, 'destroyAll'])->name('encomendas.destroy-all');
        Route::get('/encomendas/{encomenda}', [EncomendaController::class, 'show'])->name('encomendas.show');
        Route::put('/encomendas/{encomenda}/perfil', [EncomendaController::class, 'updateProfile'])->name('encomendas.profile.update');
        Route::post('/encomendas/{encomenda}/duplicar', [EncomendaController::class, 'duplicate'])->name('encomendas.duplicate');
        Route::put('/encomendas/{encomenda}/adiar', [EncomendaController::class, 'postpone'])->name('encomendas.postpone');
        Route::delete('/encomendas/{encomenda}/adiar', [EncomendaController::class, 'clearPostpone'])->name('encomendas.postpone.clear');
        Route::delete('/encomendas/{encomenda}', [EncomendaController::class, 'destroy'])->name('encomendas.destroy');
        Route::post('/entregas/atribuicoes', [EntregaController::class, 'storeAtribuicao'])->name('entregas.atribuicoes.store');
        Route::post('/entregas/atribuicoes/massa', [EntregaController::class, 'storeAtribuicoesBulk'])->name('entregas.atribuicoes.bulk');
        Route::put('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'updateAtribuicao'])->name('entregas.atribuicoes.update');
        Route::delete('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'destroyAtribuicao'])->name('entregas.atribuicoes.destroy');
    });
});
