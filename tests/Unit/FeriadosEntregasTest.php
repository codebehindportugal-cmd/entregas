<?php

namespace Tests\Unit;

use App\Models\Corporate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeriadosEntregasTest extends TestCase
{
    private function empresa(array $dias, string $morada = 'Rua X, Caldas da Rainha'): Corporate
    {
        return new Corporate([
            'empresa' => 'Teste',
            'morada_entrega' => $morada,
            'dias_entrega' => $dias,
            'periodicidade_entrega' => 'semanal',
        ]);
    }

    private function entregas(Corporate $c, string $de, string $ate): array
    {
        $out = [];
        for ($d = Carbon::parse($de); $d->lte(Carbon::parse($ate)); $d->addDay()) {
            if ($dia = $c->diaEntregaOriginalParaData($d)) {
                $out[$d->toDateString()] = $dia;
            }
        }

        return $out;
    }

    public function test_feriado_nacional_a_segunda_empurra_segunda_e_quarta(): void
    {
        // 2026-10-05 (segunda) = Implantacao da Republica
        $c = $this->empresa(['Segunda', 'Quarta']);

        $this->assertSame([
            '2026-10-06' => 'Segunda',
            '2026-10-08' => 'Quarta',
        ], $this->entregas($c, '2026-10-05', '2026-10-11'));
    }

    public function test_feriado_nacional_a_quarta_so_empurra_quarta(): void
    {
        // 2026-12-01 = terca; 2026-12-08 = terca. Usar 2027-12-08 (quarta).
        $c = $this->empresa(['Segunda', 'Quarta']);

        $this->assertSame([
            '2027-12-06' => 'Segunda',
            '2027-12-09' => 'Quarta',
        ], $this->entregas($c, '2027-12-06', '2027-12-12'));
    }

    public function test_so_segunda_com_feriado_passa_para_terca(): void
    {
        $c = $this->empresa(['Segunda']);

        $this->assertSame(['2026-10-06' => 'Segunda'], $this->entregas($c, '2026-10-05', '2026-10-11'));
    }

    public function test_todos_os_dias_uteis_nao_entrega_no_feriado(): void
    {
        $c = $this->empresa(['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta']);

        $this->assertSame([
            '2026-10-06' => 'Terca',
            '2026-10-07' => 'Quarta',
            '2026-10-08' => 'Quinta',
            '2026-10-09' => 'Sexta',
        ], $this->entregas($c, '2026-10-05', '2026-10-11'));
    }

    public function test_semana_sem_feriado_fica_igual(): void
    {
        $c = $this->empresa(['Segunda', 'Quarta']);

        $this->assertSame([
            '2026-10-12' => 'Segunda',
            '2026-10-14' => 'Quarta',
        ], $this->entregas($c, '2026-10-12', '2026-10-18'));
    }

    public function test_sexta_empurrada_para_o_fim_de_semana_nao_entrega(): void
    {
        $c = $this->empresa(['Segunda', 'Sexta']);

        $this->assertSame(['2026-10-06' => 'Segunda'], $this->entregas($c, '2026-10-05', '2026-10-11'));
    }
}
