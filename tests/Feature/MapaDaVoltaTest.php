<?php

namespace Tests\Feature;

use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use App\Models\Zona;
use App\Models\ZonaHorario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MapaDaVoltaTest extends TestCase
{
    use RefreshDatabase;

    private User $joao;

    protected function setUp(): void
    {
        parent::setUp();
        // Quarta-feira sem feriados.
        Carbon::setTestNow('2026-10-14 08:00:00');
        $this->joao = User::create(['name' => 'João', 'email' => 'joao@teste.pt', 'password' => bcrypt('x'), 'role' => 'colaborador', 'ativo' => true]);
        $this->zonaDe($this->joao, 'Zona de Lisboa');
    }

    /** A zona passa a ser feita por este colaborador as quartas. */
    private function zonaDe(User $user, string $nome): Zona
    {
        $zona = Zona::where('nome', $nome)->firstOrFail();
        ZonaHorario::updateOrCreate(['zona_id' => $zona->id, 'dia_semana' => 'Quarta'], ['user_id' => $user->id]);

        return $zona;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mostra_as_paragens_pela_ordem_da_rota_com_empresas_e_b2c(): void
    {
        $this->empresa('Siemens', 'Rua da Barruncheira 6', '2790-073', 'Carnaxide', ordem: 2);
        $this->empresa('Novartis', 'Av. da República 50', '1050-094', 'Lisboa', ordem: 1);
        $this->clienteB2c('Maria Santos', 'Rua do Pólo Sul 2', '1990-096', 'Lisboa', ordem: 3);

        $pagina = $this->actingAs($this->joao)->get(route('mapa-volta'))->assertOk();

        $pagina->assertSeeInOrder(['Novartis', 'Siemens', 'Maria Santos']);
        $paragens = $pagina->viewData('partes')[0]['paragens']->pluck('morada')->all();
        $this->assertSame([
            'Av. da República 50, 1050-094, Lisboa, Portugal',
            'Rua da Barruncheira 6, 2790-073, Carnaxide, Portugal',
            'Rua do Pólo Sul 2, 1990-096, Lisboa, Portugal',
        ], $paragens);

        $navegar = urldecode($pagina->viewData('partes')[0]['navegar']);
        $this->assertStringContainsString('destination=Rua do Pólo Sul 2', str_replace('+', ' ', $navegar));
        $this->assertStringContainsString('waypoints=Av. da República 50, 1050-094, Lisboa, Portugal|Rua da Barruncheira 6', str_replace('+', ' ', $navegar));
        $this->assertStringNotContainsString('origin=', $navegar);
        $this->assertStringContainsString('output=embed', $pagina->viewData('partes')[0]['embed']);
    }

    public function test_parte_do_armazem_quando_esta_configurado(): void
    {
        config(['entregas.origem_rota' => 'Armazém Horta da Maria, Óbidos']);
        $this->empresa('Novartis', 'Av. da República 50', '1050-094', 'Lisboa', ordem: 1);

        $pagina = $this->actingAs($this->joao)->get(route('mapa-volta'))->assertOk()->assertSee('Parte do armazém');

        $this->assertStringContainsString('origin=Armaz', $pagina->viewData('partes')[0]['navegar']);
    }

    public function test_volta_grande_e_dividida_em_partes_de_dez_que_continuam_umas_das_outras(): void
    {
        foreach (range(1, 12) as $i) {
            $this->empresa("Empresa {$i}", "Rua {$i}", '1000-00'.($i % 10), 'Lisboa', ordem: $i);
        }

        $partes = $this->actingAs($this->joao)->get(route('mapa-volta'))->assertOk()->viewData('partes');

        $this->assertCount(2, $partes);
        $this->assertCount(10, $partes[0]['paragens']);
        $this->assertCount(2, $partes[1]['paragens']);
        $this->assertStringContainsString('origin=Rua+10', $partes[1]['navegar']);
    }

    public function test_as_ja_entregues_saem_do_mapa_mas_ficam_na_lista(): void
    {
        $novartis = $this->empresa('Novartis', 'Av. da República 50', '1050-094', 'Lisboa', ordem: 1);
        $this->empresa('Siemens', 'Rua da Barruncheira 6', '2790-073', 'Carnaxide', ordem: 2);
        RegistoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $novartis->id, 'user_id' => $this->joao->id, 'data_entrega' => '2026-10-14', 'status' => 'entregue']);

        $pagina = $this->actingAs($this->joao)->get(route('mapa-volta'))->assertOk()->assertSee('Novartis')->assertSee('Entregue');
        $this->assertSame(['Siemens · Carnaxide'], $pagina->viewData('partes')[0]['paragens']->pluck('nome')->all());

        $todas = $this->actingAs($this->joao)->get(route('mapa-volta', ['todas' => 1]))->viewData('partes')[0]['paragens'];
        $this->assertCount(2, $todas);
    }

    public function test_paragem_sem_morada_e_avisada_e_fica_fora_do_mapa(): void
    {
        $this->empresa('Sem Morada', null, null, null, ordem: 1);
        $this->empresa('Novartis', 'Av. da República 50', '1050-094', 'Lisboa', ordem: 2);

        $pagina = $this->actingAs($this->joao)->get(route('mapa-volta'))->assertOk()->assertSee('não tem morada');

        $this->assertSame(['Novartis · Lisboa'], $pagina->viewData('partes')[0]['paragens']->pluck('nome')->all());
    }

    public function test_colaborador_so_ve_a_sua_volta_e_admin_ve_a_de_qualquer_um(): void
    {
        $rita = User::create(['name' => 'Rita', 'email' => 'rita@teste.pt', 'password' => bcrypt('x'), 'role' => 'colaborador', 'ativo' => true]);
        $admin = User::create(['name' => 'André', 'email' => 'andre@teste.pt', 'password' => bcrypt('x'), 'role' => 'admin', 'ativo' => true]);
        $this->empresa('Da Rita', 'Rua A 1', '1000-001', 'Lisboa', ordem: 1, user: $rita);

        $this->actingAs($this->joao)->get(route('mapa-volta', ['user_id' => $rita->id]))
            ->assertOk()->assertDontSee('Da Rita')->assertSee('Sem entregas atribuídas');

        $this->actingAs($admin)->get(route('mapa-volta', ['user_id' => $rita->id]))
            ->assertOk()->assertSee('Da Rita');
    }

    public function test_noutro_dia_mostra_a_volta_desse_dia(): void
    {
        $this->empresa('Só à quarta', 'Rua A 1', '1000-001', 'Lisboa', ordem: 1);

        $this->actingAs($this->joao)->get(route('mapa-volta', ['data' => '2026-10-12']))
            ->assertOk()->assertSee('Sem entregas atribuídas');
    }

    private function empresa(string $nome, ?string $morada, ?string $cp, ?string $cidade, int $ordem, ?User $user = null): Corporate
    {
        $corporate = Corporate::factory()->create([
            'empresa' => $nome,
            'sucursal' => $cidade,
            'dias_entrega' => ['Quarta'],
            'morada_entrega' => $morada,
            'fatura_morada' => null,
            'cp_entrega' => $cp,
            'cidade_entrega' => $cidade,
        ]);

        $zona = $user ? $this->zonaDe($user, 'Zona Norte') : Zona::where('nome', 'Zona de Lisboa')->first();
        AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'zona_id' => $zona->id, 'dia_semana' => 'Quarta', 'ordem' => $ordem]);

        return $corporate;
    }

    private function clienteB2c(string $nome, string $morada, string $cp, string $cidade, int $ordem): WooOrder
    {
        $order = WooOrder::create([
            'woo_id' => 5000 + $ordem, 'source_type' => 'order', 'status' => 'processing', 'billing_name' => $nome,
            'billing_phone' => '912345678', 'dia_entrega' => 'quarta', 'total' => 30,
            'raw_payload' => ['shipping' => ['address_1' => $morada, 'postcode' => $cp, 'city' => $cidade]],
        ]);

        AtribuicaoEntrega::create(['tipo' => 'b2c', 'woo_order_id' => $order->id, 'zona_id' => Zona::where('nome', 'Zona de Lisboa')->first()->id, 'dia_semana' => 'Quarta', 'ordem' => $ordem]);

        return $order;
    }
}
