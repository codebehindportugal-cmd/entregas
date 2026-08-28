<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'custo_envio')) {
                // Custo de transporte POR ENTREGA (com IVA se precos_incluem_iva).
                $table->decimal('custo_envio', 8, 2)->nullable()->after('preco_cabaz');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'custo_envio')) {
                $table->dropColumn('custo_envio');
            }
        });
    }
};
