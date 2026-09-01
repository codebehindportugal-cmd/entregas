<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparacaoCabazController;
use App\Http\Controllers\ComprasController;
use App\Http\Controllers\CorporateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefinicoesMoloniController;
use App\Http\Controllers\DespesaController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\RenovacaoController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EquipaController;
use App\Http\Controllers\FaturacaoController;
use App\Http\Controllers\FaturasController;
use App\Http\Controllers\FrutaEpocaController;
use App\Http\Controllers\ViaturaController;
use App\Http\Controllers\ListaCabazController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\TabelaPrecoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

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
        Route::get('/margens-cabazes', ComparacaoCabazController::class)->name('comparacao-cabazes.index');
        Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');
        Route::post('/produtos/sync', [ProdutoController::class, 'sync'])->name('produtos.sync');
        Route::put('/produtos/{produto}', [ProdutoController::class, 'update'])->name('produtos.update');
        Route::post('/produtos/{produto}/atualizar-site', [ProdutoController::class, 'updateSite'])->name('produtos.update-site');
        Route::get('/lista-cabazes', [ListaCabazController::class, 'index'])->name('lista-cabazes.index');
        Route::post('/lista-cabazes', [ListaCabazController::class, 'store'])->name('lista-cabazes.store');
        Route::get('/lista-cabazes/{listaCabaz}', [ListaCabazController::class, 'edit'])->name('lista-cabazes.edit');
        Route::put('/lista-cabazes/{listaCabaz}', [ListaCabazController::class, 'update'])->name('lista-cabazes.update');
        Route::delete('/lista-cabazes/{listaCabaz}', [ListaCabazController::class, 'destroy'])->name('lista-cabazes.destroy');
        Route::get('/faturas', [FaturasController::class, 'index'])->name('faturas.index');
        Route::get('/faturas/{fatura}/pdf', [FaturasController::class, 'pdf'])->name('faturas.pdf');
        Route::post('/faturas/{fatura}/enviada', [FaturasController::class, 'enviada'])->name('faturas.enviada');
        Route::get('/definicoes-moloni', [DefinicoesMoloniController::class, 'index'])->name('definicoes-moloni.index');
        Route::put('/definicoes-moloni', [DefinicoesMoloniController::class, 'update'])->name('definicoes-moloni.update');
        Route::get('/viaturas', [ViaturaController::class, 'index'])->name('viaturas.index');
        Route::post('/viaturas', [ViaturaController::class, 'store'])->name('viaturas.store');
        Route::put('/viaturas/{viatura}', [ViaturaController::class, 'update'])->name('viaturas.update');
        Route::delete('/viaturas/{viatura}', [ViaturaController::class, 'destroy'])->name('viaturas.destroy');

        Route::get('/fruta-epoca', [FrutaEpocaController::class, 'index'])->name('fruta-epoca.index');
        Route::post('/fruta-epoca', [FrutaEpocaController::class, 'store'])->name('fruta-epoca.store');
        Route::delete('/fruta-epoca', [FrutaEpocaController::class, 'destroy'])->name('fruta-epoca.destroy');
        Route::get('/compras', ComprasController::class)->name('compras.index');
        Route::post('/compras/precos', [ComprasController::class, 'updatePrecos'])->name('compras.precos.update');
        Route::get('/despesas/pdf', [DespesaController::class, 'exportarPdf'])->name('despesas.pdf');
        Route::get('/despesas/csv', [DespesaController::class, 'exportarCsv'])->name('despesas.csv');
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
        Route::get('/renovacoes', [RenovacaoController::class, 'index'])->name('renovacoes.index');
        Route::post('/renovacoes/{encomenda}', [RenovacaoController::class, 'store'])->name('renovacoes.store');
        Route::put('/renovacoes/{encomenda}/enviada', [RenovacaoController::class, 'marcarEnviada'])->name('renovacoes.enviada');
        Route::get('/encomendas', [EncomendaController::class, 'index'])->name('encomendas.index');
        Route::post('/encomendas/sync', [EncomendaController::class, 'sync'])->name('encomendas.sync');
        Route::delete('/encomendas/limpar-todas', [EncomendaController::class, 'destroyAll'])->name('encomendas.destroy-all');
        Route::get('/encomendas/nova', [EncomendaController::class, 'create'])->name('encomendas.create');
        Route::post('/encomendas/nova', [EncomendaController::class, 'store'])->name('encomendas.store');
        Route::get('/encomendas/{encomenda}', [EncomendaController::class, 'show'])->name('encomendas.show');
        Route::put('/encomendas/{encomenda}/perfil', [EncomendaController::class, 'updateProfile'])->name('encomendas.profile.update');
        Route::post('/encomendas/{encomenda}/duplicar', [EncomendaController::class, 'duplicate'])->name('encomendas.duplicate');
        Route::put('/encomendas/{encomenda}/pausar', [EncomendaController::class, 'pause'])->name('encomendas.pause');
        Route::put('/encomendas/{encomenda}/retomar', [EncomendaController::class, 'resume'])->name('encomendas.resume');
        Route::put('/encomendas/{encomenda}/adiar', [EncomendaController::class, 'postpone'])->name('encomendas.postpone');
        Route::delete('/encomendas/{encomenda}/adiar', [EncomendaController::class, 'clearPostpone'])->name('encomendas.postpone.clear');
        Route::post('/encomendas/{encomenda}/concluir-wordpress', [EncomendaController::class, 'complete'])->name('encomendas.complete');
        Route::post('/encomendas/{encomenda}/fatura-moloni', [FaturacaoController::class, 'subscricao'])->name('encomendas.fatura-moloni');
        Route::put('/encomendas/{encomenda}/produtos-fatura', [FaturacaoController::class, 'produtosB2c'])->name('encomendas.produtos-fatura');
        Route::post('/empresas/faturar', [FaturacaoController::class, 'empresas'])->name('corporates.faturar');
        Route::delete('/encomendas/{encomenda}', [EncomendaController::class, 'destroy'])->name('encomendas.destroy');
        Route::post('/entregas/atribuicoes', [EntregaController::class, 'storeAtribuicao'])->name('entregas.atribuicoes.store');
        Route::post('/entregas/atribuicoes/massa', [EntregaController::class, 'storeAtribuicoesBulk'])->name('entregas.atribuicoes.bulk');
        Route::put('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'updateAtribuicao'])->name('entregas.atribuicoes.update');
        Route::delete('/entregas/atribuicoes/{atribuicao}', [EntregaController::class, 'destroyAtribuicao'])->name('entregas.atribuicoes.destroy');
    });
});
