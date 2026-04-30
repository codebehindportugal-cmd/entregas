<?php

namespace Database\Factories;

use App\Models\CorporateHistorico;
use App\Models\Corporate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorporateHistorico>
 */
class CorporateHistoricoFactory extends Factory
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
            'data' => fake()->date(),
            'texto' => fake()->paragraph(),
        ];
    }
}
