<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparacao_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('preparacao_items', 'remessa_document_id')) {
                // Nas sucursais entregues por terceiros sai tambem uma guia de
                // remessa, alem da de transporte. Guardar o id em separado
                // evita emitir duas vezes a mesma.
                $table->unsignedBigInteger('remessa_document_id')->nullable()->after('guia_document_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preparacao_items', function (Blueprint $table): void {
            if (Schema::hasColumn('preparacao_items', 'remessa_document_id')) {
                $table->dropColumn('remessa_document_id');
            }
        });
    }
};
