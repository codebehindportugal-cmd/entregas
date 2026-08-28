<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'valor_ciclo')) {
                // Valor acordado para o ciclo de faturacao (4 semanas), com IVA.
                // Tem prioridade sobre preco_cabaz / preco_venda_peca na fatura.
                $table->decimal('valor_ciclo', 10, 2)->nullable()->after('preco_cabaz');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'valor_ciclo')) {
                $table->dropColumn('valor_ciclo');
            }
        });
    }
};
