<?php

namespace Database\Factories;

use App\Models\WooProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WooProduct>
 */
class WooProductFactory extends Factory
{
    protected $model = WooProduct::class;

    public function definition(): array
    {
        return [
            'woo_id' => $this->faker->unique()->numberBetween(1000, 99999),
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug(2),
            'type' => 'simple',
            'status' => 'publish',
            'stock_status' => 'instock',
            'purchasable' => true,
            'em_epoca' => true,
            'disponivel_compra' => true,
            'regular_price' => 2.50,
            'unidade_venda' => 'unidade',
            'formato_qtd' => 1,
            'formato_unidade' => 'un',
            'qtd_min' => 1,
            'unidades_confirmadas' => true,
            'raw_payload' => [],
        ];
    }

    /** Vendido em embalagem de peso, por defeito 500 g. */
    public function aoPeso(float $kg = 0.5): self
    {
        return $this->state(fn (): array => [
            'unidade_venda' => 'peso',
            'formato_qtd' => $kg,
            'formato_unidade' => 'kg',
        ]);
    }
}
