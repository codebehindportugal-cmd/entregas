<?php

namespace Database\Factories;

use App\Models\RegistoEntrega;
use App\Models\Corporate;
use App\Models\User;
use App\Models\WooOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistoEntrega>
 */
class RegistoEntregaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => 'corporate',
            'corporate_id' => Corporate::factory(),
            'woo_order_id' => null,
            'user_id' => User::factory(),
            'data_entrega' => now()->toDateString(),
            'status' => 'pendente',
            'hora_entrega' => null,
            'nota' => null,
            'fotos' => [],
        ];
    }

    public function b2c(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => 'b2c',
            'corporate_id' => null,
            'woo_order_id' => WooOrder::factory(),
        ]);
    }
}
