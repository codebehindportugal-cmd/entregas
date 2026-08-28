<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('woo_orders', 'fatura_document_id')) {
                $table->unsignedBigInteger('fatura_document_id')->nullable()->after('raw_payload');
            }

            if (! Schema::hasColumn('woo_orders', 'fatura_tipo')) {
                $table->string('fatura_tipo')->nullable()->after('fatura_document_id');
            }

            if (! Schema::hasColumn('woo_orders', 'fatura_emitida_em')) {
                $table->timestamp('fatura_emitida_em')->nullable()->after('fatura_tipo');
            }

            if (! Schema::hasColumn('woo_orders', 'cabaz_itens_faturados')) {
                // Snapshot dos produtos que compuseram o cabaz composto faturado.
                $table->json('cabaz_itens_faturados')->nullable()->after('fatura_emitida_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            foreach (['fatura_document_id', 'fatura_tipo', 'fatura_emitida_em', 'cabaz_itens_faturados'] as $coluna) {
                if (Schema::hasColumn('woo_orders', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
