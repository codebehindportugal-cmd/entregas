<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'ciclo_inicio')) {
                $table->date('ciclo_inicio')->nullable()->after('preco_cabaz');
            }
            if (! Schema::hasColumn('corporates', 'referencia_cliente')) {
                $table->string('referencia_cliente')->nullable()->after('ciclo_inicio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            foreach (['ciclo_inicio', 'referencia_cliente'] as $coluna) {
                if (Schema::hasColumn('corporates', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
