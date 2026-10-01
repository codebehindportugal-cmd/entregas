<?php

namespace Tests\Unit;

use App\Services\OrganizadorDeVoltas;
use Tests\TestCase;

class OrganizadorDeVoltasTest extends TestCase
{
    public function test_le_os_horarios_das_empresas(): void
    {
        $this->assertSame([9 * 60, 18 * 60], OrganizadorDeVoltas::janela(null));
        $this->assertSame([9 * 60, 18 * 60], OrganizadorDeVoltas::janela('Ind'));
        $this->assertSame([0, 8 * 60], OrganizadorDeVoltas::janela('até ás 8h'));
        $this->assertSame([0, 7 * 60 + 31], OrganizadorDeVoltas::janela('até ás 7:31'));
        $this->assertSame([0, 7 * 60], OrganizadorDeVoltas::janela('7h'));
        $this->assertSame([0, 9 * 60 + 15], OrganizadorDeVoltas::janela('09:15'));
        $this->assertSame([9 * 60, 11 * 60], OrganizadorDeVoltas::janela('9 e 11h'));
        $this->assertSame([9 * 60 + 30, 18 * 60], OrganizadorDeVoltas::janela('depois das 9:30'));
        // Hora limite a tarde: e a hora de fecho, e continua a nao abrir antes das 9h.
        $this->assertSame([9 * 60, 17 * 60 + 30], OrganizadorDeVoltas::janela('até ás 17:30'));
    }

    public function test_volta_de_lisboa_comeca_pelas_entregas_cedo_e_respeita_o_horario(): void
    {
        $paragens = collect([
            ['chave' => 'mafra', 'cp' => '2640-001', 'horario' => null],
            ['chave' => 'coruche', 'cp' => '2100-100', 'horario' => 'até ás 17:30'],
            ['chave' => 'benavente', 'cp' => '2130-001', 'horario' => 'ind'],
            ['chave' => 'alverca', 'cp' => '2615-001', 'horario' => null],
            ['chave' => 'saldanha8', 'cp' => '1050-001', 'horario' => 'até ás 8h'],
            ['chave' => 'oriente7', 'cp' => '1990-096', 'horario' => '7h'],
            ['chave' => 'benfica', 'cp' => '1500-001', 'horario' => null],
            ['chave' => 'sem-cp', 'cp' => null, 'horario' => null],
        ]);

        $volta = app(OrganizadorDeVoltas::class)->organizar($paragens);
        $ordem = $volta->pluck('chave')->all();

        $this->assertSame(['oriente7', 'saldanha8'], array_slice($ordem, 0, 2));
        $this->assertSame('sem-cp', end($ordem));
        $this->assertCount(0, $volta->where('atrasada', true));

        $porChave = $volta->keyBy('chave');
        $this->assertLessThanOrEqual('07:00', $porChave['oriente7']['hora_prevista']);
        $this->assertLessThanOrEqual('08:00', $porChave['saldanha8']['hora_prevista']);
        foreach (['mafra', 'coruche', 'benavente', 'alverca', 'benfica'] as $chave) {
            $this->assertGreaterThanOrEqual('09:00', $porChave[$chave]['hora_prevista'], $chave);
        }
        $this->assertLessThanOrEqual('17:30', $porChave['coruche']['hora_prevista']);
        // Sai das Caldas com tempo para chegar ao Oriente antes das 7h.
        $this->assertLessThan('06:00', $volta->first()['saida']);
    }

    public function test_a_simulacao_mostra_quem_fica_fora_de_horas(): void
    {
        $volta = app(OrganizadorDeVoltas::class)->simular(collect([
            ['chave' => 'leiria', 'cp' => '2410-001', 'horario' => null],
            ['chave' => 'lisboa7', 'cp' => '1050-001', 'horario' => '7h'],
        ]));

        $this->assertTrue($volta->firstWhere('chave', 'lisboa7')['atrasada']);
    }

    public function test_a_volta_pode_partir_de_outro_sitio(): void
    {
        $paragens = collect([
            ['chave' => 'espinho', 'cp' => '4500-001', 'horario' => null],
            ['chave' => 'lionesa', 'cp' => '4465-671', 'horario' => '7h'],
        ]);

        $dasCaldas = app(OrganizadorDeVoltas::class)->organizar($paragens);
        $doPorto = app(OrganizadorDeVoltas::class)->organizar($paragens, '4470');

        $this->assertLessThan('05:00', $dasCaldas->first()['saida']);
        $this->assertGreaterThan('06:00', $doPorto->first()['saida']);
        $this->assertSame('lionesa', $doPorto->first()['chave']);
        $this->assertCount(0, $doPorto->where('atrasada', true));
    }
}
