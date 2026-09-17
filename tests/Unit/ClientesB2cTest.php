<?php

namespace Tests\Unit;

use App\Services\ClientesB2c;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientesB2cTest extends TestCase
{
    public static function telefones(): array
    {
        return [
            'so digitos' => ['912345678', '912345678'],
            'com espacos' => ['912 345 678', '912345678'],
            'com +351' => ['+351 912 345 678', '912345678'],
            'com 00351' => ['00351912345678', '912345678'],
            'com 351 sem +' => ['351912345678', '912345678'],
            'fixo' => ['262-123-456', '262123456'],
            'estrangeiro' => ['+44 7700 900123', '447700900123'],
            'curto' => ['12345', null],
            'vazio' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('telefones')]
    public function test_normaliza_telefones(?string $telefone, ?string $esperado): void
    {
        $this->assertSame($esperado, ClientesB2c::normalizarTelefone($telefone));
    }

    public function test_mesmo_nome_ignora_acentos_e_nomes_parciais(): void
    {
        $this->assertTrue(ClientesB2c::mesmoNome('João Gonçalves', 'joao goncalves'));
        $this->assertTrue(ClientesB2c::mesmoNome('Joana Costa', 'Joana'));
        $this->assertTrue(ClientesB2c::mesmoNome('Joana Costa', null));
        $this->assertFalse(ClientesB2c::mesmoNome('Joana Costa', 'Rui Silva'));
    }
}
