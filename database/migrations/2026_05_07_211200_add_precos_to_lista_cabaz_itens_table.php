<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lista_cabaz_itens', function (Blueprint $table): void {
            if (! Schema::hasColumn('lista_cabaz_itens', 'tabela_preco_item_id')) {
                $table->foreignId('tabela_preco_item_id')
                    ->nullable()
                    ->after('unidade')
                    ->constrained('tabela_preco_itens')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lista_cabaz_itens', 'preco_unitario')) {
                $table->decimal('preco_unitario', 8, 4)->nullable()->after('tabela_preco_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lista_cabaz_itens', function (Blueprint $table): void {
            if (Schema::hasColumn('lista_cabaz_itens', 'tabela_preco_item_id')) {
                $table->dropConstrainedForeignId('tabela_preco_item_id');
            }

            if (Schema::hasColumn('lista_cabaz_itens', 'preco_unitario')) {
                $table->dropColumn('preco_unitario');
            }
        });
    }
};
