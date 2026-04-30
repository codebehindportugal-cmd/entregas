<?php

namespace Database\Factories;

use App\Models\PreparacaoItem;
use App\Models\Corporate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreparacaoItem>
 */
class PreparacaoItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_preparacao' => now()->toDateString(),
            'tipo' => 'corporate',
            'corporate_id' => Corporate::factory(),
            'woo_order_id' => null,
            'feito' => false,
            'feito_at' => null,
            'feito_por' => null,
        ];
    }
}
