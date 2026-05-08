<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lista_cabaz_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('lista_cabaz_itens', 'peso_unitario_kg')) {
                $table->decimal('peso_unitario_kg', 8, 4)->nullable()->after('unidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lista_cabaz_itens', function (Blueprint $table): void {
            if (Schema::hasColumn('lista_cabaz_itens', 'peso_unitario_kg')) {
                $table->dropColumn('peso_unitario_kg');
            }
        });
    }
};
