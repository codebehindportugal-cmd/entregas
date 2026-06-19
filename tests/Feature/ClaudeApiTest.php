<?php

namespace Tests\Feature;

use App\Models\Corporate;
use App\Models\RegistoEntrega;
use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClaudeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();

        config(['services.claude.api_token' => 'test-token']);
    }

    public function test_rejects_requests_without_claude_token(): void
    {
        $this->getJson('/api/claude/subscricoes')
            ->assertUnauthorized();
    }

    public function test_lists_subscriptions_ending_soon(): void
    {
        Carbon::setTestNow('2026-06-19 10:00:00');

        WooOrder::factory()->create([
            'source_type' => 'subscription',
            'status' => 'active',
            'billing_name' => 'Maria Silva',
            'delivery_dates' => ['2026-06-12', '2026-06-19', '2026-06-26'],
            'subscription_ends_at' => '2026-06-26',
        ]);
        WooOrder::factory()->create([
            'source_type' => 'subscription',
            'status' => 'active',
            'delivery_dates' => ['2026-08-01'],
            'subscription_ends_at' => '2026-08-01',
        ]);

        $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('/api/claude/subscricoes?dias=14')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('subscricoes.0.cliente', 'Maria Silva')
            ->assertJsonPath('subscricoes.0.fim_subscricao', '2026-06-26');

        Carbon::setTestNow();
    }

    public function test_downloads_monthly_corporate_map_pdf(): void
    {
        $corporate = Corporate::factory()->create([
            'empresa' => 'Empresa Teste',
            'sucursal' => null,
            'dias_entrega' => ['Segunda'],
            'frutas_por_dia' => [
                'Segunda' => [
                    'banana' => 4,
                    'maca' => 3,
                ],
            ],
            'pastelaria_por_dia' => [
                'Segunda' => [
                    'croissant' => 2,
                ],
            ],
            'produtos_mensais' => [],
        ]);
        RegistoEntrega::factory()->create([
            'corporate_id' => $corporate->id,
            'data_entrega' => '2026-06-01',
            'status' => 'entregue',
        ]);

        $this->withHeader('X-Claude-Api-Key', 'test-token')
            ->get("/api/claude/empresas/{$corporate->id}/mapa-mensal.pdf?mes=2026-06")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
