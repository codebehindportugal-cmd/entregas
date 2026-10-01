<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            // A entrega fica noutra empresa (ex.: a de Evora e deixada nos
            // Correos de Lisboa). Nas voltas e no mapa conta a morada dessa.
            $table->foreignId('entregar_em_corporate_id')->nullable()->after('parceiro_local')
                ->constrained('corporates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entregar_em_corporate_id');
        });
    }
};
