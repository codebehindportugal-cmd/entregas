<?php

namespace Database\Factories;

use App\Models\WooOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WooOrder>
 */
class WooOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'woo_id' => fake()->unique()->numberBetween(1000, 999999),
            'status' => 'processing',
            'total' => fake()->randomFloat(2, 10, 150),
            'billing_name' => fake()->name(),
            'billing_phone' => fake()->phoneNumber(),
            'billing_email' => fake()->safeEmail(),
            'line_items' => [],
            'postponed_until' => null,
            'cancelled_delivery_dates' => [],
            'subscription_ends_at' => null,
            'excluded_products' => [],
            'dia_entrega' => fake()->randomElement(['quarta', 'sabado']),
            'ciclo_entrega' => 'semanal',
            'raw_payload' => [],
            'synced_at' => now(),
        ];
    }
}
