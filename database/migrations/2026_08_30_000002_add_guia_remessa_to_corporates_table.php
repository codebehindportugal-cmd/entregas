<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'guia_remessa')) {
                // Sucursais em que a entrega e feita por um terceiro: alem da
                // guia de transporte, tem de sair tambem guia de remessa.
                $table->boolean('guia_remessa')->default(false)->after('moloni_guia_ref');
            }

            if (! Schema::hasColumn('corporates', 'transportador')) {
                // Quem faz a entrega nessas sucursais (sai nas observacoes da
                // guia de remessa).
                $table->string('transportador')->nullable()->after('guia_remessa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            foreach (['guia_remessa', 'transportador'] as $coluna) {
                if (Schema::hasColumn('corporates', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
