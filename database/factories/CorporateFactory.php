<?php

namespace Database\Factories;

use App\Models\Corporate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Corporate>
 */
class CorporateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa' => fake()->company(),
            'sucursal' => fake()->optional()->city(),
            'dias_entrega' => fake()->randomElements(['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'], 2),
            'periodicidade_entrega' => 'semanal',
            'quinzenal_referencia' => null,
            'horario_entrega' => '09:00-11:00',
            'responsavel_nome' => fake()->name(),
            'responsavel_telefone' => fake()->phoneNumber(),
            'fatura_nome' => fake()->company(),
            'fatura_nif' => (string) fake()->numberBetween(100000000, 999999999),
            'fatura_email' => fake()->companyEmail(),
            'fatura_morada' => fake()->address(),
            'numero_caixas' => fake()->numberBetween(1, 8),
            'peso_total' => fake()->randomFloat(2, 3, 45),
            'frutas' => [
                'banana' => fake()->numberBetween(0, 20),
                'maca' => fake()->numberBetween(0, 20),
                'pera' => fake()->numberBetween(0, 20),
                'laranja' => fake()->numberBetween(0, 20),
                'kiwi' => fake()->numberBetween(0, 20),
                'uvas' => fake()->randomFloat(2, 0, 20),
                'fruta_epoca' => fake()->numberBetween(0, 20),
                'frutos_secos' => fake()->randomFloat(2, 0, 5),
                'mirtilos' => fake()->randomFloat(2, 0, 5),
                'framboesas' => fake()->randomFloat(2, 0, 5),
                'amoras' => fake()->randomFloat(2, 0, 5),
                'morangos' => fake()->randomFloat(2, 0, 5),
            ],
            'notas' => fake()->optional()->sentence(),
            'ativo' => true,
        ];
    }
}
