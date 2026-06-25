<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparacaoCabazController;
use App\Http\Controllers\ComprasController;
use App\Http\Controllers\CorporateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DespesaController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EquipaController;
use App\Http\Controllers\ListaCabazController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\TabelaPrecoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/faturas/{encomenda}', [EncomendaController::class, 'publicInvoice'])
    ->middleware('signed')
    ->name('encomendas.invoice.public');

Route::get('/relatorios/download', function (Illuminate\Http\Request $request) {
    abort_unless($request->hasValidSignature(), 403);
    $path = (string) $request->query('path', '');
    abort_unless(str_starts_with($path, 'relatorios/') && ! str_contains($path, '..'), 403);
    abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

    return response()->file(
        \Illuminate\Support\Facades\Storage::disk('local')->path($path),
        ['Content-Type' => 'application/pdf'],
    );
})->name('relatorio.download');

Route::post('/webhooks/woocommerce', [WebhookController::class, 'woocommerce'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.woocommerce');

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'create'])->name('login');
    Route::get('/login', [AuthController::class, 'create']);
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/minhas-entregas', [EntregaController::class, 'minhasEntregas'])->name('minhas-entregas.index');
    Route::put('/minhas-entregas/ordem', [EntregaController::class, 'updateOrdemMinhasEntregas'])->name('minhas-entregas.ordem.update');
    Route::get('/minhas-entregas/{registoEntrega}', [EntregaController::class, 'show'])->name('minhas-entregas.show');
    Route::put('/minhas-entregas/{registoEntrega}', [EntregaController::class, 'update'])->name('minhas-entregas.update');
    Route::delete('/minhas-entregas/{registoEntrega}/fotos/{index}', [EntregaController::class, 'destroyFoto'])->name('minhas-entregas.fotos.destroy');

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/corporates/exportar/json', [CorporateController::class, 'export'])->name('corporates.export');
        Route::post('/corporates/importar/json', [CorporateController::class, 'import'])->name('corporates.import');
        Route::get('/corporates/{corporate}/relatorio-mensal', [CorporateController::class, 'relatorioMensal'])->name('corporates.relatorio-mensal');
        Route::get('/corporates/{corporate}/mapa-mensal', [CorporateController::class, 'mapaMensal'])->name('corporates.mapa-mensal');
        Route::resource('/corporates', CorporateController::class);
        Route::post('/corporates/{corporate}/historico', [CorporateController::class, 'storeHistorico'])->name('corporates.historico.store');
        Route::delete('/corporates/{corporate}/historico/{historico}', [CorporateController::class, 'destroyHistorico'])->name('corporates.historico.destroy');
        Route::resource('/equipa', EquipaController::class)->except(['show']);
        Route::get('/entregas', [EntregaController::class, 'index'])->name('entregas.index');
        Route::get('/entregas/verificacao', [EntregaController::class, 'verificacao'])->name('entregas.verificacao');
        Route::get('/preparacao', [EntregaController::class, 'preparacao'])->name('preparacao.index');
        Route::put('/preparacao/{item}', [EntregaController::class, 'updatePreparacaoItem'])->name('preparacao.update');
        Route::put('/preparacao/{item}/produtos', [EntregaController::class, 'updatePreparacaoProdutos'])->name('preparacao.produtos.update');
        Route::resource('/lista-cabazes', ListaCabazController::class)
            ->parameters(['lista-cabazes' => 'listaCabaz'])
            ->except(['show']);
        Route::post('/lista-cabazes/importar', [ListaCabazController::class, 'import'])->name('lista-cabazes.import');
        Route::post('/lista-cabazes/{listaCabaz}/itens', [ListaCabazController::class, 'storeItem'])->name('lista-cabazes.itens.store');
        Route::put('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'updateItem'])->name('lista-cabazes.itens.update');
        Route::delete('/lista-cabazes/itens/{item}', [ListaCabazController::class, 'destroyItem'])->name('lista-cabazes.itens.destroy');
        Route::get('/lista-cabazes/{listaCabaz}/totais', [ListaCabazController::class, 'totais'])->name('lista-cabazes.totais');
        Route::get('/margens-cabazes', ComparacaoCabazController::class)->name('comparacao-cabazes.index');
        Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');
        Route::post('/produtos/sync', [ProdutoController::class, 'sync'])->name('produtos.sync');
        Route::put('/produtos/{produto}', [ProdutoController::class, 'update'])->name('produtos.update');
        Route::post('/produtos/{produto}/atualizar-site', [ProdutoController::class, 'updateSite'])->name('produtos.update-site');
        Route::get('/compras', ComprasController::class)->name('compras.index');
        Route::post('/compras/precos', [ComprasController::class, 'updatePrecos'])->name('compras.precos.update');
        Route::get('/despesas/pdf', [DespesaController::class, 'exportarPdf'])->name('despesas.pdf');
        Route::get('/despesas/csv', [DespesaController::class, 'exportarCsv'])->name('despesas.csv');
        Route::post('/despesas/extrair-ia', [DespesaController::class, 'extrairIa'])->name('despesas.extrair-ia');
        Route::get('/despesas/create', [DespesaController::class, 'create'])->name('despesas.create');
        Route::post('/despesas', [DespesaController::class, 'store'])->name('despesas.store');
        Route::get('/despesas/{despesa}/edit', [DespesaController::class, 'edit'])->name('despesas.edit');
        Route::patch('/despesas/{despesa}', [DespesaController::class, 'update'])->name('despesas.update');
        Route::delete('/despesas/{despesa}', [DespesaController::class, 'destroy'])->name('despesas.destroy');
        Route::get('/despesas', [DespesaController::class, 'index'])->name('despesas.index');
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
        Route::post('/encomendas/{encomenda}/concluir-wordpress', [EncomendaController::class, 'complete'])->name('encomendas.complete');
        Route::get('/encomendas/{encomenda}/fatura', [EncomendaController::class, 'invoice'])->name('encomendas.invoice');
        Route::delete('/encomendas/{encomenda}', [EncomendaController::class, 'destroy'])->name('encomendas.destroy');
        Route::post('/entregas/atribuicoes', [EntregaController::class, 'storeAtribuicao'])->name('entregas.atribuicoes.store');
        Route::post('/entregas/atribuicoes/massa', [EntregaController::class, 'storeAtribuicoesBulk'])->name('entregas.atribuicoes.bulk');
        Route::put('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'updateAtribuicao'])->name('entregas.atribuicoes.update');
        Route::delete('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'destroyAtribuicao'])->name('entregas.atribuicoes.destroy');
    });
});
