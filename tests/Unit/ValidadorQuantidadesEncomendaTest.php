<?php

namespace Tests\Unit;

use App\Models\WooProduct;
use App\Services\ValidadorQuantidadesEncomenda;
use Tests\TestCase;

/**
 * Os casos que o Andre deu: a maca vende-se a unidade, a ameixa em 500 g.
 *
 * O que se testa aqui nao e a conversao certa — e a recusa a converter quando
 * nao ha como. Uma encomenda com o quadruplo do peso e sempre pior do que uma
 * pergunta a mais ao cliente.
 */
class ValidadorQuantidadesEncomendaTest extends TestCase
{
    private ValidadorQuantidadesEncomenda $validador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validador = new ValidadorQuantidadesEncomenda;
    }

    private function maca(?float $pesoMedio = null): WooProduct
    {
        return new WooProduct([
            'name' => 'Maca Royal Gala',
            'unidade_venda' => 'unidade',
            'formato_qtd' => 1,
            'formato_unidade' => 'un',
            'peso_medio_kg' => $pesoMedio,
            'qtd_min' => 1,
        ]);
    }

    private function ameixa(float $embalagemKg = 0.5): WooProduct
    {
        return new WooProduct([
            'name' => 'Ameixa',
            'unidade_venda' => 'peso',
            'formato_qtd' => $embalagemKg,
            'formato_unidade' => 'kg',
            'qtd_min' => 1,
        ]);
    }

    public function test_produto_a_unidade_com_unidades(): void
    {
        $resultado = $this->validador->validarLinha($this->maca(), 3, 'un');

        $this->assertSame(3, $resultado['quantidade_woo']);
        $this->assertSame([], $resultado['erros']);
    }

    public function test_produto_a_unidade_sem_unidade_assume_unidades(): void
    {
        $resultado = $this->validador->validarLinha($this->maca(), 2, null);

        $this->assertSame(2, $resultado['quantidade_woo']);
        $this->assertSame('UNIDADE_ASSUMIDA', $resultado['avisos'][0]['codigo']);
    }

    public function test_kg_num_produto_a_unidade_sem_peso_medio_nao_converte(): void
    {
        $resultado = $this->validador->validarLinha($this->maca(), 1, 'kg');

        $this->assertNull($resultado['quantidade_woo']);
        $this->assertSame('CONVERSAO_IMPOSSIVEL', $resultado['erros'][0]['codigo']);
    }

    public function test_kg_num_produto_a_unidade_com_peso_medio_converte_com_aviso(): void
    {
        $resultado = $this->validador->validarLinha($this->maca(0.200), 1, 'kg');

        $this->assertSame(5, $resultado['quantidade_woo']);
        $this->assertSame('CONVERSAO_ESTIMADA', $resultado['avisos'][0]['codigo']);
    }

    public function test_kg_multiplo_da_embalagem(): void
    {
        $resultado = $this->validador->validarLinha($this->ameixa(), 2, 'kg');

        $this->assertSame(4, $resultado['quantidade_woo']);
        $this->assertSame('4 x Ameixa = 2 kg', $resultado['equivalencia']);
    }

    public function test_kg_nao_multiplo_da_embalagem_sugere_os_dois_lados(): void
    {
        $resultado = $this->validador->validarLinha($this->ameixa(), 1.3, 'kg');

        $this->assertNull($resultado['quantidade_woo']);
        $this->assertSame('QTD_NAO_MULTIPLA', $resultado['erros'][0]['codigo']);
        $this->assertSame([2, 3], array_column($resultado['erros'][0]['sugestoes'], 'quantidade_woo'));
    }

    public function test_gramas_sao_convertidas_para_kg(): void
    {
        $resultado = $this->validador->validarLinha($this->ameixa(), 1500, 'g');

        $this->assertSame(3, $resultado['quantidade_woo']);
    }

    public function test_produto_ao_peso_sem_unidade_pergunta(): void
    {
        $resultado = $this->validador->validarLinha($this->ameixa(), 2, null);

        $this->assertNull($resultado['quantidade_woo']);
        $this->assertSame('UNIDADE_EM_FALTA', $resultado['erros'][0]['codigo']);
        $this->assertNotEmpty($resultado['erros'][0]['sugestoes']);
    }

    public function test_produto_ao_peso_pedido_em_unidades_conta_embalagens(): void
    {
        $resultado = $this->validador->validarLinha($this->ameixa(), 2, 'un');

        $this->assertSame(2, $resultado['quantidade_woo']);
        $this->assertSame('INTERPRETADO_COMO_EMBALAGENS', $resultado['avisos'][0]['codigo']);
        $this->assertStringContainsString('1 kg', $resultado['avisos'][0]['mensagem']);
    }

    public function test_meia_unidade_nao_passa(): void
    {
        $resultado = $this->validador->validarLinha($this->maca(), 1.5, 'un');

        $this->assertSame('QTD_NAO_INTEIRA', $resultado['erros'][0]['codigo']);
    }

    public function test_respeita_a_quantidade_maxima(): void
    {
        $produto = $this->maca();
        $produto->qtd_max = 10;

        $resultado = $this->validador->validarLinha($produto, 12, 'un');

        $this->assertSame('FORA_DOS_LIMITES', $resultado['erros'][0]['codigo']);
    }
}
