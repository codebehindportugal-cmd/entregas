<?php

namespace Database\Factories;

use App\Models\WhatsAppLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppLog>
 */
class WhatsAppLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to' => fake()->phoneNumber(),
            'message' => fake()->sentence(),
            'status' => 'sent',
            'response' => [],
            'sent_at' => now(),
        ];
    }
}
