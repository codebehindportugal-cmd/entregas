<?php

namespace Tests\Feature;

use App\Models\Corporate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorporateValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('O driver pdo_sqlite nao esta instalado neste ambiente.');
        }

        parent::setUp();
    }

    public function test_rejeita_formatos_portugueses_invalidos_com_mensagens_em_portugues(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->from(route('corporates.create'))
            ->post(route('corporates.store'), $this->dadosEmpresa([
                'cp_entrega' => '1000000',
                'responsavel_telefone' => '12345',
                'fatura_nif' => '501964842',
            ]));

        $response
            ->assertRedirect(route('corporates.create'))
            ->assertSessionHasErrors([
                'cp_entrega' => 'O código postal deve ter o formato 0000-000.',
                'responsavel_telefone' => 'O telefone deve ser um número português válido.',
                'fatura_nif' => 'O NIF indicado não é válido.',
            ]);
    }

    public function test_aceita_formatos_portugueses_validos(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('corporates.store'), $this->dadosEmpresa([
                'cp_entrega' => '1000-001',
                'responsavel_telefone' => '+351 912 345 678',
                'fatura_nif' => '501964843',
            ]));

        $response->assertRedirect(route('corporates.index'));

        $this->assertDatabaseHas('corporates', [
            'empresa' => 'Empresa de Teste',
            'cp_entrega' => '1000-001',
            'responsavel_telefone' => '+351 912 345 678',
            'fatura_nif' => '501964843',
        ]);
    }

    public function test_campos_obrigatorios_usam_nomes_e_mensagens_em_portugues(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('corporates.store'), $this->dadosEmpresa([
                'empresa' => '',
                'dias_entrega' => [],
            ]));

        $response->assertSessionHasErrors([
            'empresa' => 'O campo empresa é obrigatório.',
            'dias_entrega' => 'O campo dias de entrega é obrigatório.',
        ]);
    }

    public function test_importacao_e_filtro_mensal_usam_form_requests_em_portugues(): void
    {
        $admin = User::factory()->admin()->create();
        $corporate = Corporate::factory()->create();

        $this->actingAs($admin)
            ->post(route('corporates.import'))
            ->assertSessionHasErrors([
                'ficheiro' => 'O campo ficheiro é obrigatório.',
            ]);

        $this->actingAs($admin)
            ->get(route('corporates.relatorio-mensal', [$corporate, 'mes' => '09-2026']))
            ->assertSessionHasErrors([
                'mes' => 'O campo mês deve respeitar o formato Y-m.',
            ]);
    }

    private function dadosEmpresa(array $alteracoes = []): array
    {
        return [
            'empresa' => 'Empresa de Teste',
            'dias_entrega' => ['Segunda'],
            'periodicidade_entrega' => 'semanal',
            'numero_caixas' => 1,
            ...$alteracoes,
        ];
    }
}
