<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'fatura_conjunta')) {
                // Sucursais do mesmo NIF que querem tudo numa so fatura. Sem esta
                // marca, cada sucursal continua a ter a sua fatura.
                $table->boolean('fatura_conjunta')->default(false)->after('fatura_morada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'fatura_conjunta')) {
                $table->dropColumn('fatura_conjunta');
            }
        });
    }
};
