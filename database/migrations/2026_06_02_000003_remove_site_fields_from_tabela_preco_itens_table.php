<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabela_preco_itens', function (Blueprint $table): void {
            foreach (['woo_variation_id', 'woo_product_id', 'disponivel_compra', 'em_epoca', 'epoca'] as $column) {
                if (Schema::hasColumn('tabela_preco_itens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tabela_preco_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('tabela_preco_itens', 'epoca')) {
                $table->string('epoca')->nullable()->after('calibre');
            }

            if (! Schema::hasColumn('tabela_preco_itens', 'em_epoca')) {
                $table->boolean('em_epoca')->default(true)->after('epoca');
            }

            if (! Schema::hasColumn('tabela_preco_itens', 'disponivel_compra')) {
                $table->boolean('disponivel_compra')->default(true)->after('em_epoca');
            }

            if (! Schema::hasColumn('tabela_preco_itens', 'woo_product_id')) {
                $table->unsignedBigInteger('woo_product_id')->nullable()->after('disponivel_compra');
            }

            if (! Schema::hasColumn('tabela_preco_itens', 'woo_variation_id')) {
                $table->unsignedBigInteger('woo_variation_id')->nullable()->after('woo_product_id');
            }
        });
    }
};
