<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_faturas', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporate_faturas', 'enviada_em')) {
                // Marcacao MANUAL de "ja foi enviada ao cliente".
                $table->timestamp('enviada_em')->nullable()->after('emitida_em');
            }

            if (! Schema::hasColumn('corporate_faturas', 'enviada_por')) {
                $table->string('enviada_por')->nullable()->after('enviada_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporate_faturas', function (Blueprint $table): void {
            foreach (['enviada_em', 'enviada_por'] as $coluna) {
                if (Schema::hasColumn('corporate_faturas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
