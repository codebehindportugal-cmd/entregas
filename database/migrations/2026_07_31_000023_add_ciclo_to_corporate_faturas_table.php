<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_faturas', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporate_faturas', 'ciclo_ref')) {
                $table->string('ciclo_ref', 20)->nullable()->after('periodo'); // chave do ciclo (dedup)
            }
            if (! Schema::hasColumn('corporate_faturas', 'ciclo_label')) {
                $table->string('ciclo_label')->nullable()->after('ciclo_ref'); // periodo legivel do ciclo
            }
            if (! Schema::hasColumn('corporate_faturas', 'referencia_cliente')) {
                $table->string('referencia_cliente')->nullable()->after('ciclo_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporate_faturas', function (Blueprint $table): void {
            foreach (['ciclo_ref', 'ciclo_label', 'referencia_cliente'] as $coluna) {
                if (Schema::hasColumn('corporate_faturas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
