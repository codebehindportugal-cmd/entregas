<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'ciclo_entrega')) {
                $table->enum('ciclo_entrega', ['semanal', 'quinzenal'])->default('semanal')->after('dia_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (Schema::hasColumn('woo_orders', 'ciclo_entrega')) {
                $table->dropColumn('ciclo_entrega');
            }
        });
    }
};
