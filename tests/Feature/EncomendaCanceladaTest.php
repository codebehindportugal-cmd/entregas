<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntregaController;
use App\Models\PreparacaoItem;
use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EncomendaCanceladaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_encomenda_cancelada_desaparece_da_preparacao_e_das_contagens(): void
    {
        Carbon::setTestNow('2026-09-09 09:00:00');

        $cancelada = $this->criarSubscricao('cancelled', 'Cliente Cancelado');
        $subscricaoAtiva = $this->criarSubscricao('active', 'Cliente Subscricao');
        $encomendaValida = WooOrder::factory()->create([
            'source_type' => 'order',
            'status' => 'processing',
            'billing_name' => 'Cliente Encomenda',
            'dia_entrega' => 'quarta',
            'scheduled_delivery_at' => '2026-09-09',
        ]);

        PreparacaoItem::factory()->create([
            'data_preparacao' => '2026-09-09',
            'tipo' => 'b2c',
            'woo_order_id' => $cancelada->id,
            'corporate_id' => null,
            'feito' => false,
        ]);

        $preparacao = $this->app->make(EntregaController::class)->preparacao(Request::create('/preparacao', 'GET', [
            'data' => '2026-09-09',
            'mostrar_tudo' => 1,
        ]));

        $dadosPreparacao = $preparacao->getData();
        $ids = $dadosPreparacao['b2cPreparacoes']->pluck('order.id');

        $this->assertNotContains($cancelada->id, $ids);
        $this->assertContains($subscricaoAtiva->id, $ids);
        $this->assertContains($encomendaValida->id, $ids);

        $dashboard = $this->app->call([$this->app->make(DashboardController::class), '__invoke']);
        $dadosDashboard = $dashboard->getData();

        $this->assertSame(2, $dadosDashboard['preparacaoTotal']);
        $this->assertSame(2, $dadosDashboard['preparacaoPorFazer']);
        $this->assertSame(2, $dadosDashboard['b2cAtivas']);
        $this->assertSame(1, $dadosDashboard['subscricoesAtivas']);
        $this->assertSame(1, $dadosDashboard['encomendasProcessamento']);
    }

    private function criarSubscricao(string $status, string $nome): WooOrder
    {
        return WooOrder::factory()->create([
            'source_type' => 'subscription',
            'status' => $status,
            'billing_name' => $nome,
            'dia_entrega' => 'quarta',
            'first_delivery_at' => '2026-09-09',
            'delivery_dates' => ['2026-09-09'],
            'subscription_ends_at' => '2026-10-01',
        ]);
    }
}
