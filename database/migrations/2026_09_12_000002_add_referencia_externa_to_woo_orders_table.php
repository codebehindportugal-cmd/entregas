<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia das encomendas criadas pelo chat.
 *
 * O mesmo `referencia_externa` da API de faturas: se o chat repetir o pedido
 * (rede a falhar, mensagem reenviada), devolve-se a encomenda que ja existe em
 * vez de criar outra no site. Unico, para a base de dados garantir isto mesmo
 * que dois pedidos cheguem ao mesmo tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            $table->string('referencia_externa')->nullable()->unique()->after('woo_id');
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            $table->dropUnique(['referencia_externa']);
            $table->dropColumn('referencia_externa');
        });
    }
};
