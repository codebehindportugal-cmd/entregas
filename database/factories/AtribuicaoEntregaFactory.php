<?php

namespace Database\Factories;

use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AtribuicaoEntrega>
 */
class AtribuicaoEntregaFactory extends Factory
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
            'dia_semana' => fake()->randomElement(['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta']),
        ];
    }
}
