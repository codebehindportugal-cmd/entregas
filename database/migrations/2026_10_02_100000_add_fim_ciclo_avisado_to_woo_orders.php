<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            // Ultimo fim de ciclo da subscricao de que ja se avisou no ntfy
            // (cada ciclo de 4 entregas avisa uma so vez).
            $table->date('fim_ciclo_avisado')->nullable()->after('renovada_em');
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            $table->dropColumn('fim_ciclo_avisado');
        });
    }
};
