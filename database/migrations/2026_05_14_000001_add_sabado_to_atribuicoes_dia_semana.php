<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE atribuicoes MODIFY dia_semana ENUM('Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE atribuicoes MODIFY dia_semana ENUM('Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta') NOT NULL");
        }
    }
};
