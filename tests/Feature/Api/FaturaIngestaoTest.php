<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\StoreFaturasLoteApiRequest;
use App\Models\Despesa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/v1/faturas e /api/v1/faturas/lote — faturas de papel lidas no chat.
 *
 * O que estes testes guardam: o token e mesmo exigido, as linhas entram com o
 * tamanho da embalagem certo, e no lote uma fatura que falha nao arrasta as
 * outras — sem isso, fotografar dez faturas obrigava a repetir as dez.
 */
class FaturaIngestaoTest extends TestCase
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

    public function test_recusa_sem_token(): void
    {
        $this->postJson('/api/v1/faturas', $this->fatura('FT 1/1'))
            ->assertUnauthorized();

        $this->postJson('/api/v1/faturas/lote', ['faturas' => [$this->fatura('FT 1/2')]])
            ->assertUnauthorized();

        $this->assertDatabaseCount('despesas', 0);
    }

    public function test_regista_uma_fatura_com_linhas(): void
    {
        $this->comToken()
            ->postJson('/api/v1/faturas', [
                'numero_fatura' => 'FT 2026/500',
                'fornecedor' => 'Frutas do Oeste',
                'data' => '2026-09-01',
                'linhas' => [[
                    'descricao' => 'Maca Royal Gala caixa 6 kg',
                    'quantidade' => 2,
                    'unidade_compra' => 'kg',
                    'unidades_por_quantidade' => 6,
                    'preco_unitario' => 9.00,
                    'iva_percentagem' => 6,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.despesa.numero_fatura', 'FT 2026/500')
            ->assertJsonPath('dados.despesa.titulo', 'Fatura Frutas do Oeste FT 2026/500')
            ->assertJsonCount(1, 'dados.despesa.linhas');

        // 2 x 9,00 = 18,00 + 6% = 19,08
        $this->assertDatabaseHas('despesas', [
            'numero_fatura' => 'FT 2026/500',
            'categoria' => 'entrada_produtos',
            'valor' => 19.08,
        ]);

        // 2 caixas de 6 kg sao 12 kg de entrada — e o que faz o custo por kg.
        $this->assertDatabaseHas('fatura_items', [
            'descricao' => 'Maca Royal Gala caixa 6 kg',
            'quantidade' => 2,
            'unidades_por_quantidade' => 6,
            'quantidade_unidades' => 12,
        ]);
    }

    public function test_aceita_os_nomes_do_agro_para_a_embalagem(): void
    {
        $this->comToken()
            ->postJson('/api/v1/faturas', [
                'numero_fatura' => 'FT 2026/501',
                'data' => '2026-09-01',
                'linhas' => [[
                    'descricao' => 'Azeite 5 L',
                    'quantidade' => 3,
                    'conteudo_embalagem' => 5,
                    'unidade_embalagem' => 'L',
                    'preco_unitario' => 20,
                    'iva_percentagem' => 13,
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('fatura_items', [
            'descricao' => 'Azeite 5 L',
            'unidade_compra' => 'L',
            'unidades_por_quantidade' => 5,
            'quantidade_unidades' => 15,
        ]);
    }

    public function test_desconto_entra_no_preco_e_fica_dito_nas_notas(): void
    {
        $response = $this->comToken()
            ->postJson('/api/v1/faturas', [
                'numero_fatura' => 'FT 2026/502',
                'data' => '2026-09-01',
                'linhas' => [[
                    'descricao' => 'Cenoura saco 10 kg',
                    'quantidade' => 1,
                    'unidades_por_quantidade' => 10,
                    'preco_unitario' => 10,
                    'desconto_percentagem' => 10,
                    'iva_percentagem' => 6,
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('fatura_items', [
            'descricao' => 'Cenoura saco 10 kg',
            'preco_unitario' => 9,
        ]);

        $this->assertStringContainsString('desconto', (string) $response->json('avisos.0'));
        $this->assertStringContainsString(
            'desconto',
            (string) Despesa::query()->firstWhere('numero_fatura', 'FT 2026/502')?->items->first()->notas
        );
    }

    public function test_lote_regista_varias(): void
    {
        $this->comToken()
            ->postJson('/api/v1/faturas/lote', [
                'faturas' => [
                    $this->fatura('FT 2026/510'),
                    $this->fatura('FT 2026/511'),
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('dados.registadas', 2)
            ->assertJsonPath('dados.falhadas', 0)
            ->assertJsonPath('dados.faturas.0.estado', 'registada')
            ->assertJsonPath('dados.faturas.1.estado', 'registada');

        $this->assertDatabaseCount('despesas', 2);
        $this->assertDatabaseCount('fatura_items', 2);
    }

    public function test_fatura_invalida_nao_impede_as_outras(): void
    {
        $response = $this->comToken()
            ->postJson('/api/v1/faturas/lote', [
                'faturas' => [
                    $this->fatura('FT 2026/520'),
                    // Sem data e com IVA que nao existe.
                    [
                        'numero_fatura' => 'FT 2026/521',
                        'linhas' => [[
                            'descricao' => 'Pera',
                            'quantidade' => 1,
                            'preco_unitario' => 5,
                            'iva_percentagem' => 17,
                        ]],
                    ],
                    $this->fatura('FT 2026/522'),
                ],
            ]);

        $response->assertStatus(207)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('dados.registadas', 2)
            ->assertJsonPath('dados.falhadas', 1)
            ->assertJsonPath('dados.faturas.1.estado', 'erro');

        $this->assertNotEmpty($response->json('erros.faturas.1'));
        $this->assertDatabaseCount('despesas', 2);
        $this->assertDatabaseMissing('despesas', ['numero_fatura' => 'FT 2026/521']);
    }

    public function test_fatura_repetida_nao_duplica(): void
    {
        $this->comToken()->postJson('/api/v1/faturas', $this->fatura('FT 2026/530'))->assertCreated();

        $this->comToken()
            ->postJson('/api/v1/faturas/lote', [
                'faturas' => [
                    $this->fatura('FT 2026/530'),
                    $this->fatura('FT 2026/531'),
                    $this->fatura('FT 2026/531'),
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('dados.registadas', 1)
            ->assertJsonPath('dados.repetidas', 2)
            ->assertJsonPath('dados.faturas.0.estado', 'repetida')
            ->assertJsonPath('dados.faturas.2.estado', 'repetida');

        $this->assertDatabaseCount('despesas', 2);
    }

    public function test_lote_vazio_e_acima_do_maximo_sao_rejeitados(): void
    {
        $this->comToken()
            ->postJson('/api/v1/faturas/lote', ['faturas' => []])
            ->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonStructure(['erros' => ['faturas']]);

        $muitas = array_map(
            fn (int $n) => $this->fatura("FT 2026/6{$n}"),
            range(1, StoreFaturasLoteApiRequest::MAXIMO + 1)
        );

        $this->comToken()
            ->postJson('/api/v1/faturas/lote', ['faturas' => $muitas])
            ->assertStatus(422);

        $this->assertDatabaseCount('despesas', 0);
    }

    private function comToken(): self
    {
        return $this->withHeader('Authorization', 'Bearer test-token');
    }

    /** @return array<string, mixed> */
    private function fatura(string $numero): array
    {
        return [
            'numero_fatura' => $numero,
            'fornecedor' => 'Frutas do Oeste',
            'data' => '2026-09-01',
            'linhas' => [[
                'descricao' => 'Batata saco 20 kg',
                'quantidade' => 1,
                'unidade_compra' => 'kg',
                'unidades_por_quantidade' => 20,
                'preco_unitario' => 12,
                'iva_percentagem' => 6,
            ]],
        ];
    }
}
