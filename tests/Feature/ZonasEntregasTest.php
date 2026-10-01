<?php

namespace Tests\Feature;

use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use App\Models\Zona;
use App\Models\ZonaHorario;
use App\Models\ZonaSubstituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Entregas por zonas (Andre, 01/10/2026): as entregas pertencem a zonas e
 * quem entrega e quem faz a zona nesse dia (horario ou substituicao).
 */
class ZonasEntregasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $joao;

    private User $rita;

    private Zona $norte;

    private Zona $lisboa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 08:00:00'); // quarta

        $this->admin = $this->user('André', 'admin');
        $this->joao = $this->user('João');
        $this->rita = $this->user('Rita');
        $this->norte = Zona::where('nome', 'Zona Norte')->firstOrFail();
        $this->lisboa = Zona::where('nome', 'Zona de Lisboa')->firstOrFail();

        $this->horario($this->norte, 'Quarta', $this->joao);
        $this->horario($this->lisboa, 'Quarta', $this->rita);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_as_quatro_zonas_existem_com_area_e_codigos_postais(): void
    {
        $this->assertSame(['Zona Norte', 'Zona Centro', 'Zona Oeste', 'Zona de Lisboa', 'Zona de Cascais'], Zona::orderBy('ordem')->pluck('nome')->all());
        $this->assertSame('Zona Oeste', Zona::sugeridaPara('2510-451', Zona::all())?->nome); // Praia d'El Rey
        $this->assertSame('Zona Oeste', Zona::sugeridaPara('2540-001', Zona::all())?->nome); // Bombarral
        $this->assertSame('Zona Centro', Zona::sugeridaPara('2400-118', Zona::all())?->nome); // Leiria
        $this->assertSame('Zona Norte', Zona::sugeridaPara('4100-138', Zona::all())?->nome);
        $this->assertSame('Zona Centro', Zona::sugeridaPara('2480-001', Zona::all())?->nome); // Porto de Mós
        $this->assertSame('Zona de Cascais', Zona::sugeridaPara('1495-150', Zona::all())?->nome); // Miraflores
        $this->assertSame('Zona de Lisboa', Zona::sugeridaPara('2100-120', Zona::all())?->nome); // Coruche
    }

    public function test_ao_sabado_lisboa_e_cascais_sao_de_uma_pessoa_e_o_oeste_de_outra(): void
    {
        Carbon::setTestNow('2026-10-17 08:00:00'); // sabado
        $tiago = $this->user('Tiago');
        $cascais = Zona::where('nome', 'Zona de Cascais')->first();
        $oeste = Zona::where('nome', 'Zona Oeste')->first();

        $this->actingAs($this->admin)->put(route('zonas.horario'), ['horario' => [
            $this->lisboa->id => ['Sabado' => 'u:'.$this->rita->id],
            $cascais->id => ['Sabado' => 'z:'.$this->lisboa->id],
            $oeste->id => ['Sabado' => 'u:'.$tiago->id],
        ]])->assertSessionHasNoErrors();

        $this->clienteSabado('Cliente Lisboa', $this->lisboa);
        $this->clienteSabado('Cliente Cascais', $cascais);
        $this->clienteSabado('Cliente Óbidos', $oeste);

        $this->actingAs($this->rita)->get(route('minhas-entregas.index'))
            ->assertOk()->assertSee('Cliente Lisboa')->assertSee('Cliente Cascais')->assertDontSee('Cliente Óbidos');
        $this->actingAs($tiago)->get(route('minhas-entregas.index'))
            ->assertOk()->assertSee('Cliente Óbidos')->assertDontSee('Cliente Lisboa');
    }

    private function clienteSabado(string $nome, Zona $zona): void
    {
        static $wooId = 8000;
        $order = WooOrder::create(['woo_id' => ++$wooId, 'source_type' => 'order', 'status' => 'processing', 'billing_name' => $nome, 'dia_entrega' => 'sabado', 'total' => 30]);
        AtribuicaoEntrega::create(['tipo' => 'b2c', 'woo_order_id' => $order->id, 'zona_id' => $zona->id, 'dia_semana' => 'Sabado']);
    }

    public function test_cascais_vai_com_quem_faz_a_zona_centro_tambem_nas_ferias(): void
    {
        $centro = Zona::where('nome', 'Zona Centro')->first();
        $cascais = Zona::where('nome', 'Zona de Cascais')->first();
        $tiago = $this->user('Tiago');

        $this->actingAs($this->admin)->put(route('zonas.horario'), ['horario' => [
            $centro->id => ['Quarta' => 'u:'.$tiago->id],
            $cascais->id => ['Quarta' => 'z:'.$centro->id],
        ]])->assertSessionHasNoErrors();

        $this->assertSame($tiago->id, $cascais->fresh()->colaboradorIdEm('2026-10-14'));

        ZonaSubstituicao::create(['zona_id' => $centro->id, 'user_id' => $this->joao->id, 'inicio' => '2026-10-21', 'fim' => '2026-10-21']);
        $this->assertSame($this->joao->id, $cascais->fresh()->colaboradorIdEm('2026-10-21'));

        $this->entrega('Em Cascais', $cascais);
        $this->actingAs($tiago)->get(route('minhas-entregas.index'))->assertSee('Em Cascais');
        $this->actingAs($this->admin)->get(route('zonas.index'))->assertOk()->assertSee('= Zona Centro');
    }

    public function test_zonas_que_vao_uma_com_a_outra_nao_ficam_em_ciclo(): void
    {
        $centro = Zona::where('nome', 'Zona Centro')->first();
        $cascais = Zona::where('nome', 'Zona de Cascais')->first();
        ZonaHorario::create(['zona_id' => $centro->id, 'dia_semana' => 'Quarta', 'acompanha_zona_id' => $cascais->id]);
        ZonaHorario::create(['zona_id' => $cascais->id, 'dia_semana' => 'Quarta', 'acompanha_zona_id' => $centro->id]);

        $this->assertNull($cascais->fresh()->colaboradorIdEm('2026-10-14'));
    }

    public function test_quem_faz_a_zona_segue_o_horario_e_as_ferias(): void
    {
        ZonaSubstituicao::create(['zona_id' => $this->norte->id, 'user_id' => $this->rita->id, 'inicio' => '2026-10-20', 'fim' => '2026-10-31', 'nota' => 'Férias do João']);
        $norte = $this->norte->fresh();

        $this->assertSame($this->joao->id, $norte->colaboradorIdEm('2026-10-14'));
        $this->assertSame($this->rita->id, $norte->colaboradorIdEm('2026-10-21'));
        $this->assertSame($this->joao->id, $norte->colaboradorIdEm('2026-11-04'));
        $this->assertNull($norte->colaboradorIdEm('2026-10-15')); // quinta: ninguem
    }

    public function test_colaborador_ve_as_entregas_da_zona_que_lhe_calha(): void
    {
        $this->entrega('Sonae', $this->norte);
        $this->entrega('Novartis', $this->lisboa);

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))
            ->assertOk()->assertSee('Sonae')->assertDontSee('Novartis');
        $this->actingAs($this->rita)->get(route('minhas-entregas.index'))
            ->assertOk()->assertSee('Novartis')->assertDontSee('Sonae');
    }

    public function test_nas_ferias_as_entregas_passam_para_quem_substitui(): void
    {
        $this->entrega('Sonae', $this->norte);
        $corporate = Corporate::where('empresa', 'Sonae')->first();

        // O registo de hoje ja foi criado para o João (ex.: Estado das entregas).
        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertSee('Sonae');

        ZonaSubstituicao::create(['zona_id' => $this->norte->id, 'user_id' => $this->rita->id, 'inicio' => '2026-10-14', 'fim' => '2026-10-14']);

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertDontSee('Sonae');
        $this->actingAs($this->rita)->get(route('minhas-entregas.index'))->assertSee('Sonae');

        $registos = RegistoEntrega::where('corporate_id', $corporate->id)->whereDate('data_entrega', '2026-10-14')->get();
        $this->assertCount(1, $registos);
        $this->assertSame($this->rita->id, (int) $registos->first()->user_id);
    }

    public function test_entrega_ja_feita_nao_muda_de_colaborador(): void
    {
        $this->entrega('Sonae', $this->norte);
        $corporate = Corporate::where('empresa', 'Sonae')->first();
        RegistoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'user_id' => $this->joao->id, 'data_entrega' => '2026-10-14', 'status' => 'entregue']);

        ZonaSubstituicao::create(['zona_id' => $this->norte->id, 'user_id' => $this->rita->id, 'inicio' => '2026-10-14', 'fim' => '2026-10-14']);
        $this->actingAs($this->rita)->get(route('minhas-entregas.index'))->assertSee('Sonae');

        $this->assertSame($this->joao->id, (int) RegistoEntrega::where('corporate_id', $corporate->id)->first()->user_id);
    }

    public function test_feriado_a_segunda_entrega_a_terca_com_quem_faz_a_zona_a_segunda(): void
    {
        Carbon::setTestNow('2026-10-06 08:00:00'); // terca; segunda 05/10 e feriado
        $this->horario($this->norte, 'Segunda', $this->joao);
        $this->entrega('Só à segunda', $this->norte, 'Segunda');

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertOk()->assertSee('Só à segunda');
    }

    public function test_volta_empurrada_pelo_feriado_fica_com_quem_faz_o_dia_original(): void
    {
        Carbon::setTestNow('2026-10-06 08:00:00'); // terca; segunda 05/10 e feriado
        $this->horario($this->lisboa, 'Segunda', $this->joao);
        $this->horario($this->lisboa, 'Terca', $this->rita);
        $this->entrega('Volta de segunda', $this->lisboa, 'Segunda');

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertSee('Volta de segunda');
        $this->actingAs($this->rita)->get(route('minhas-entregas.index'))->assertDontSee('Volta de segunda');
        $this->actingAs($this->admin)->get(route('mapa-volta', ['zona_id' => $this->lisboa->id]))->assertSee('Zona de Lisboa · João');
        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Terca']))->assertSeeInOrder(['Zona de Lisboa', 'Faz:', 'João']);
    }

    public function test_atribuir_em_massa_a_uma_zona_e_mudar_de_zona_vai_para_o_fim(): void
    {
        $corporate = $this->empresa('Sonae', '4450-208');
        $order = WooOrder::create(['woo_id' => 777, 'source_type' => 'order', 'status' => 'processing', 'billing_name' => 'Maria', 'dia_entrega' => 'quarta', 'total' => 30]);

        $this->actingAs($this->admin)->post(route('entregas.atribuicoes.bulk'), [
            'dia_semana' => 'Quarta', 'zona_id' => $this->norte->id,
            'corporate_ids' => [$corporate->id], 'woo_order_ids' => [$order->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, AtribuicaoEntrega::where('zona_id', $this->norte->id)->count());

        $atribuicao = AtribuicaoEntrega::where('corporate_id', $corporate->id)->first();
        $atribuicao->update(['ordem' => 3]);

        $this->actingAs($this->admin)->put(route('entregas.atribuicoes.update', $atribuicao), [
            'tipo' => 'corporate', 'corporate_id' => $corporate->id, 'dia_semana' => 'Quarta', 'zona_id' => $this->lisboa->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->lisboa->id, (int) $atribuicao->fresh()->zona_id);
        $this->assertNull($atribuicao->fresh()->ordem);
    }

    public function test_ordem_da_volta_e_por_zona(): void
    {
        $a = $this->entrega('Primeira', $this->norte);
        $b = $this->entrega('Segunda empresa', $this->norte);

        $this->actingAs($this->admin)->put(route('entregas.ordem.update'), [
            'zona_id' => $this->norte->id,
            'ordens' => [$b->id => 1, $a->id => 2],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertSeeInOrder(['Segunda empresa', 'Primeira']);
    }

    public function test_entregas_novas_entram_sozinhas_na_zona_do_codigo_postal(): void
    {
        $porto = $this->empresa('No Porto', '4100-138');
        $cascais = $this->empresa('Em Cascais', '2750-300');
        $semCp = $this->empresa('Sem código', null);
        $order = WooOrder::create([
            'woo_id' => 9911, 'source_type' => 'order', 'status' => 'processing', 'billing_name' => 'Cliente Matosinhos',
            'dia_entrega' => 'quarta', 'total' => 30,
            'raw_payload' => ['shipping' => ['address_1' => 'Rua A', 'postcode' => '4450-208', 'city' => 'Matosinhos']],
        ]);

        // Ninguem atribuiu nada: o João (Zona Norte a quarta) ja as tem.
        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))
            ->assertOk()->assertSee('No Porto')->assertSee('Cliente Matosinhos')->assertDontSee('Em Cascais');

        $this->assertSame($this->norte->id, (int) AtribuicaoEntrega::where('corporate_id', $porto->id)->value('zona_id'));
        $this->assertTrue(AtribuicaoEntrega::where('woo_order_id', $order->id)->first()->zona_automatica);
        $this->assertSame('Zona de Cascais', AtribuicaoEntrega::where('corporate_id', $cascais->id)->first()->zona->nome);
        $this->assertFalse(AtribuicaoEntrega::where('corporate_id', $semCp->id)->exists());

        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()->assertSee('Sem zona')->assertSee('auto');
    }

    public function test_o_que_se_muda_a_mao_fica_e_o_automatico_segue_os_codigos_postais(): void
    {
        $manual = $this->empresa('Mudada à mão', '4100-138');
        $auto = $this->empresa('Automática', '4200-001');
        app(\App\Services\EntregasDoDia::class)->garantirZonas(now());

        $atribuicao = AtribuicaoEntrega::where('corporate_id', $manual->id)->first();
        $this->actingAs($this->admin)->put(route('entregas.atribuicoes.update', $atribuicao), [
            'tipo' => 'corporate', 'corporate_id' => $manual->id, 'dia_semana' => 'Quarta', 'zona_id' => $this->lisboa->id,
        ])->assertSessionHasNoErrors();

        // O Porto passa a ser da Zona de Lisboa nos codigos postais.
        $this->norte->update(['codigos_postais' => '3500-3899']);
        $this->lisboa->update(['codigos_postais' => $this->lisboa->codigos_postais.', 4000-4999']);
        app(\App\Services\EntregasDoDia::class)->garantirZonas(now());

        $this->assertSame($this->lisboa->id, (int) AtribuicaoEntrega::where('corporate_id', $auto->id)->value('zona_id'));
        $this->assertSame($this->lisboa->id, (int) $atribuicao->fresh()->zona_id);
        $this->assertFalse($atribuicao->fresh()->zona_automatica);

        // E voltando atras, a mudada a mao nao volta para o Norte.
        $this->norte->update(['codigos_postais' => '3500-3899, 4000-5199']);
        $this->lisboa->update(['codigos_postais' => '1000-1494, 1500-1999, 2100-2199, 2600-2739']);
        app(\App\Services\EntregasDoDia::class)->garantirZonas(now());

        $this->assertSame($this->norte->id, (int) AtribuicaoEntrega::where('corporate_id', $auto->id)->value('zona_id'));
        $this->assertSame($this->lisboa->id, (int) $atribuicao->fresh()->zona_id);
    }

    public function test_atribuicoes_antigas_por_converter_nao_sao_mexidas_pela_automatica(): void
    {
        $corporate = $this->empresa('Antiga', '4100-138');
        $antiga = AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'user_id' => $this->rita->id, 'dia_semana' => 'Quarta']);

        app(\App\Services\EntregasDoDia::class)->garantirZonas(now());

        $this->assertNull($antiga->fresh()->zona_id);
        $this->assertSame(1, AtribuicaoEntrega::where('corporate_id', $corporate->id)->count());
    }

    public function test_empresa_entregue_por_parceiro_local_fica_fora_das_voltas(): void
    {
        $faro = $this->empresa('Correos Faro', '8005-489');
        $faro->update(['parceiro_local' => true]);
        $loule = $this->empresa('Loulé', '8100-302');
        $this->entrega('Sonae', $this->norte);
        AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $faro->id, 'zona_id' => $this->norte->id, 'dia_semana' => 'Quarta']);

        $this->actingAs($this->joao)->get(route('minhas-entregas.index'))->assertSee('Sonae')->assertDontSee('Correos Faro');

        $rotas = $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()->assertSee('+ 1 por parceiros locais');
        $nomes = $rotas->viewData('entregas')->pluck('nome')->all();
        $this->assertNotContains('Correos Faro', $nomes);
        $this->assertContains('Loulé', $nomes);
        $this->assertTrue($rotas->viewData('rotas')->flatMap(fn ($r) => $r['paragens'])->pluck('nome')->doesntContain('Correos Faro'));

        app(\App\Services\EntregasDoDia::class)->garantirZonas(now());
        $this->assertSame(1, AtribuicaoEntrega::where('corporate_id', $faro->id)->count());
    }

    public function test_ficha_da_empresa_guarda_parceiro_local(): void
    {
        $empresa = $this->empresa('Pinto e Cruz Funchal', '9020-040');

        $this->actingAs($this->admin)->get(route('corporates.edit', $empresa))->assertOk()->assertSee('Entregue por parceiro local');
        $this->assertTrue(in_array('parceiro_local', $empresa->getFillable(), true));
    }

    public function test_comando_agendado_atribui_os_proximos_dias(): void
    {
        $corporate = $this->empresa('Para a semana', '2410-001', ['Segunda']);

        $this->artisan('entregas:atribuir-zonas', ['--dias' => 7])->assertSuccessful();

        $this->assertSame('Zona Centro', AtribuicaoEntrega::where('corporate_id', $corporate->id)->first()->zona->nome);
    }

    public function test_converter_atribuicoes_antigas_de_um_colaborador_para_uma_zona(): void
    {
        $tiago = $this->user('Tiago');
        $corporate = $this->empresa('Antiga', '4000-001', ['Segunda', 'Quarta']);
        $antigaSeg = AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'user_id' => $tiago->id, 'dia_semana' => 'Segunda']);
        $antigaQua = AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'user_id' => $tiago->id, 'dia_semana' => 'Quarta']);

        $this->actingAs($this->admin)->get(route('zonas.index'))->assertOk()->assertSee('Passar as entregas antigas para zonas')->assertSee('Tiago');

        $this->actingAs($this->admin)->post(route('zonas.converter'), ['zona' => [$tiago->id => $this->norte->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->norte->id, (int) $antigaSeg->fresh()->zona_id);
        $this->assertSame($this->norte->id, (int) $antigaQua->fresh()->zona_id);
        // A segunda nao tinha ninguem: fica o Tiago. A quarta ja era do João e fica.
        $this->assertSame($tiago->id, (int) ZonaHorario::where('zona_id', $this->norte->id)->where('dia_semana', 'Segunda')->value('user_id'));
        $this->assertSame($this->joao->id, (int) ZonaHorario::where('zona_id', $this->norte->id)->where('dia_semana', 'Quarta')->value('user_id'));
    }

    public function test_pagina_das_zonas_guarda_horario_e_recusa_substituicoes_sobrepostas(): void
    {
        $this->actingAs($this->admin)->put(route('zonas.horario'), [
            'horario' => [$this->norte->id => ['Segunda' => 'u:'.$this->rita->id, 'Quarta' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->rita->id, $this->norte->fresh()->colaboradorIdEm('2026-10-12'));
        $this->assertNull($this->norte->fresh()->colaboradorIdEm('2026-10-14'));

        $dados = ['zona_id' => $this->norte->id, 'user_id' => $this->joao->id, 'inicio' => '2026-10-20', 'fim' => '2026-10-25'];
        $this->actingAs($this->admin)->post(route('zonas.substituicoes.store'), $dados)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('zonas.substituicoes.store'), ['inicio' => '2026-10-24', 'fim' => '2026-10-30'] + $dados)
            ->assertSessionHasErrors('inicio');

        $this->assertSame(1, ZonaSubstituicao::count());
    }

    public function test_colaborador_nao_entra_nas_zonas(): void
    {
        $this->actingAs($this->joao)->get(route('zonas.index'))->assertRedirect();
        $this->actingAs($this->joao)->post(route('zonas.converter'), ['zona' => []])->assertRedirect();
        $this->assertSame(0, ZonaSubstituicao::count());
    }

    public function test_pagina_das_rotas_mostra_as_voltas_por_zona_com_quem_as_faz(): void
    {
        $this->entrega('Sonae', $this->norte);
        ZonaSubstituicao::create(['zona_id' => $this->lisboa->id, 'user_id' => $this->joao->id, 'inicio' => '2026-10-10', 'fim' => '2026-10-20']);

        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()
            ->assertSee('Zona Norte')->assertSee('Zona de Cascais')
            ->assertSee('substituição até 20/10')
            ->assertSee('Ninguém faz esta zona neste dia');
    }

    public function test_organizar_a_volta_poe_primeiro_as_entregas_cedo_e_mostra_as_horas(): void
    {
        $mafra = $this->empresa('Mafra Lda', '2640-001');
        $cedo = $this->empresa('Oriente 7h', '1990-096');
        $cedo->update(['horario_entrega' => '7h']);
        $coruche = $this->empresa('Coruche SA', '2100-100');
        $coruche->update(['horario_entrega' => 'até às 17:30']);
        foreach ([$mafra, $cedo, $coruche] as $i => $empresa) {
            AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $empresa->id, 'zona_id' => $this->lisboa->id, 'dia_semana' => 'Quarta', 'ordem' => $i + 1]);
        }

        // Pela ordem antiga a entrega das 7h chegava tarde.
        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()
            ->assertSee('fora do horário com esta ordem');

        $this->actingAs($this->admin)
            ->post(route('entregas.organizar'), ['dia_semana' => 'Quarta', 'zona_id' => $this->lisboa->id])
            ->assertSessionHasNoErrors();

        $ordem = AtribuicaoEntrega::where('zona_id', $this->lisboa->id)->orderBy('ordem')->get()->map(fn ($a) => $a->corporate->empresa)->all();
        $this->assertSame('Oriente 7h', $ordem[0]);

        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()
            ->assertDontSee('fora do horário com esta ordem')
            ->assertSee('sai das Caldas');
    }

    public function test_subscricao_sem_entrega_nesse_dia_nao_aparece_na_volta(): void
    {
        // Subscricao que ja acabou: a atribuicao ficou guardada, mas nao ha entrega.
        $acabada = WooOrder::create([
            'woo_id' => 555, 'source_type' => 'subscription', 'status' => 'subscricao', 'billing_name' => 'Cliente Acabada',
            'dia_entrega' => 'quarta', 'ciclo_entrega' => 'semanal', 'first_delivery_at' => '2026-08-05',
            'delivery_dates' => ['2026-08-05', '2026-08-12', '2026-08-19', '2026-08-26'], 'renovada_em' => '2026-08-26', 'total' => 1,
        ]);
        AtribuicaoEntrega::create(['tipo' => 'b2c', 'woo_order_id' => $acabada->id, 'zona_id' => $this->lisboa->id, 'dia_semana' => 'Quarta']);

        $this->actingAs($this->admin)->get(route('entregas.index', ['dia' => 'Quarta']))
            ->assertOk()
            ->assertDontSee('Cliente Acabada');
    }

    private function user(string $nome, string $role = 'colaborador'): User
    {
        return User::create(['name' => $nome, 'email' => mb_strtolower(str_replace(['é', 'ã'], ['e', 'a'], $nome)).'@teste.pt', 'password' => bcrypt('x'), 'role' => $role, 'ativo' => true]);
    }

    private function horario(Zona $zona, string $dia, User $user): void
    {
        ZonaHorario::updateOrCreate(['zona_id' => $zona->id, 'dia_semana' => $dia], ['user_id' => $user->id]);
    }

    private function empresa(string $nome, ?string $cp, array $dias = ['Quarta']): Corporate
    {
        return Corporate::factory()->create([
            'empresa' => $nome, 'sucursal' => null, 'dias_entrega' => $dias,
            'morada_entrega' => 'Rua X 1', 'cp_entrega' => $cp, 'cidade_entrega' => null,
        ]);
    }

    private function entrega(string $nome, Zona $zona, string $dia = 'Quarta'): AtribuicaoEntrega
    {
        $corporate = $this->empresa($nome, null, [$dia]);

        return AtribuicaoEntrega::create(['tipo' => 'corporate', 'corporate_id' => $corporate->id, 'zona_id' => $zona->id, 'dia_semana' => $dia]);
    }
}
