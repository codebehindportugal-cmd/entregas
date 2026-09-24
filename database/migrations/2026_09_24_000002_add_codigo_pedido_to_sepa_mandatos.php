<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codigo do cliente no numero do pedido SEPA (MsgId).
 * Numero = codigo + AA + DD + MM da data de cobranca (ex.: Uriage 26 a 06/10/2026 -> 26260610).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sepa_mandatos', function (Blueprint $table): void {
            if (! Schema::hasColumn('sepa_mandatos', 'codigo_pedido')) {
                $table->string('codigo_pedido', 10)->nullable()->after('mandato_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sepa_mandatos', function (Blueprint $table): void {
            if (Schema::hasColumn('sepa_mandatos', 'codigo_pedido')) {
                $table->dropColumn('codigo_pedido');
            }
        });
    }
};
