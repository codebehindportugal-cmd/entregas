<?php

namespace Database\Factories;

use App\Models\RegistoEntrega;
use App\Models\Corporate;
use App\Models\User;
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
            'corporate_id' => Corporate::factory(),
            'user_id' => User::factory(),
            'data_entrega' => now()->toDateString(),
            'status' => 'pendente',
            'hora_entrega' => null,
            'nota' => null,
            'fotos' => [],
        ];
    }
}
