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
        User::updateOrCreate(['email' => 'admin@entregas.test'], [
            'name' => 'Admin Entregas',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'cor' => '#F59E0B',
            'ativo' => true,
        ]);

        User::updateOrCreate(['email' => 'colaborador@entregas.test'], [
            'name' => 'Colaborador Demo',
            'password' => Hash::make('password'),
            'role' => 'colaborador',
            'cor' => '#22C55E',
            'ativo' => true,
        ]);
    }
}
