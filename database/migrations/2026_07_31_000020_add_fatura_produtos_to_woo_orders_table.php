<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('woo_orders', 'fatura_produtos')) {
                $table->json('fatura_produtos')->nullable()->after('cabaz_itens_faturados');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('woo_orders', 'fatura_produtos')) {
                $table->dropColumn('fatura_produtos');
            }
        });
    }
};
