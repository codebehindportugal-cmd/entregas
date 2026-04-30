<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@hortadamaria.com'], [
            'name' => 'Admin Entregas',
            'password' => Hash::make('Ateneya2026!'),
            'role' => 'admin',
            'cor' => '#F59E0B',
            'ativo' => true,
        ]);

        User::updateOrCreate(['email' => 'andre.mendes@codebehind.pt'], [
            'name' => 'André Mendes',
            'password' => Hash::make('Ateneya2026!'),
            'role' => 'colaborador',
            'cor' => '#22C55E',
            'ativo' => true,
        ]);
    }
}
