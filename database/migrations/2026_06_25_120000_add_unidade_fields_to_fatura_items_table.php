<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_items', function (Blueprint $table): void {
            $table->string('unidade_compra', 20)->default('un')->after('quantidade');
            $table->decimal('unidades_por_quantidade', 10, 3)->default(1)->after('unidade_compra');
            $table->decimal('quantidade_unidades', 10, 3)->default(1)->after('unidades_por_quantidade');
        });
    }

    public function down(): void
    {
        Schema::table('fatura_items', function (Blueprint $table): void {
            $table->dropColumn(['unidade_compra', 'unidades_por_quantidade', 'quantidade_unidades']);
        });
    }
};
