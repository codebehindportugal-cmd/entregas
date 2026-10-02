<?php

namespace Tests\Feature;

use App\Models\WooOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvisoFimSubscricaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();

        config([
            'ntfy.enabled' => true,
            'ntfy.url' => 'https://ntfy.test',
            'ntfy.topic' => 'horta-teste',
        ]);
    }

    private function fakeNtfy(): void
    {
        Http::fake(['ntfy.test/*' => Http::response(['id' => 'x'])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function subscricao(array $atributos = []): WooOrder
    {
        return WooOrder::factory()->create(array_merge([
            'woo_id' => 123,
            'source_type' => 'subscription',
            'status' => 'active',
            'billing_name' => 'Maria Silva',
            'dia_entrega' => 'quarta',
            'ciclo_entrega' => 'semanal',
            // Ciclo de 4 entregas: 12/08, 19/08, 26/08 e 02/09.
            'first_delivery_at' => '2026-08-12',
            'delivery_dates' => [],
            'subscription_ends_at' => null,
            'renovacao_automatica' => false,
        ], $atributos));
    }

    public function test_avisa_no_dia_da_ultima_entrega(): void
    {
        $this->fakeNtfy();
        Carbon::setTestNow('2026-09-02 07:15:00');
        $this->subscricao();

        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://ntfy.test/horta-teste'
            && $request->header('Title')[0] === 'Horta: 1 subscricao terminou'
            && $request->header('Priority')[0] === 'high'
            && str_contains($request->body(), '#123 Maria Silva, ultima entrega 02/09: sem renovacao automatica'));
        $this->assertSame('2026-09-02', WooOrder::first()->fim_ciclo_avisado->toDateString());
    }

    public function test_avisa_uma_vez_por_ciclo(): void
    {
        $this->fakeNtfy();
        Carbon::setTestNow('2026-09-02 07:15:00');
        $this->subscricao();

        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();
        Carbon::setTestNow('2026-09-03 07:15:00');
        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_nao_avisa_a_meio_do_ciclo_nem_em_pausa_nem_cancelada(): void
    {
        $this->fakeNtfy();
        Carbon::setTestNow('2026-08-26 07:15:00');
        $this->subscricao();

        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        Carbon::setTestNow('2026-09-02 07:15:00');
        WooOrder::query()->delete();
        $this->subscricao(['status' => 'cancelled']);
        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_diz_se_a_renovacao_ja_foi_criada(): void
    {
        $this->fakeNtfy();
        Carbon::setTestNow('2026-09-02 07:15:00');
        $nova = WooOrder::factory()->create(['woo_id' => 456, 'source_type' => 'order', 'status' => 'pending']);
        $this->subscricao([
            'renovacao_automatica' => true,
            'renovada_em' => '2026-09-02',
            'renovacao_woo_order_id' => $nova->id,
        ]);

        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->header('Priority')[0] === 'default'
            && str_contains($request->body(), 'renovada (#456)'));
    }

    public function test_se_o_ntfy_falhar_tenta_no_dia_seguinte(): void
    {
        Http::fake(['ntfy.test/*' => Http::sequence()->push('erro', 500)->push(['id' => 'x'])]);
        Carbon::setTestNow('2026-09-02 07:15:00');
        $this->subscricao();

        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();
        $this->assertNull(WooOrder::first()->fim_ciclo_avisado);

        Carbon::setTestNow('2026-09-03 07:15:00');
        $this->artisan('subscricoes:avisar-fim')->assertSuccessful();

        $this->assertSame('2026-09-02', WooOrder::first()->fim_ciclo_avisado->toDateString());
    }
}
