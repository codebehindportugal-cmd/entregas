<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Como e que cada produto se vende.
 *
 * O WooCommerce so aceita quantidades inteiras de linhas, por isso "2 kg de
 * ameixa" tem de virar 4 x "Ameixa 500g" antes de sair daqui. Sem estes campos
 * nao ha maneira de saber se o 2 que o cliente escreveu no WhatsApp sao quilos,
 * unidades ou embalagens — e adivinhar da encomendas com o dobro ou o quadruplo
 * do que foi pedido.
 *
 * `custo_quantidade`/`custo_unidade` ja existiam mas sao outra coisa: e o custo
 * de compra ao fornecedor, para a margem, nao a forma como se vende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_products', function (Blueprint $table): void {
            // 'unidade' (maca, vende-se a peca) ou 'peso' (ameixa, embalagem de 500 g).
            $table->string('unidade_venda', 10)->default('unidade')->after('custo_unidade');
            // O que cabe numa linha do Woo: 1 (un) ou 0.500 (kg).
            $table->decimal('formato_qtd', 10, 3)->default(1)->after('unidade_venda');
            $table->string('formato_unidade', 5)->default('un')->after('formato_qtd');
            // Peso medio de uma unidade. So para converter kg -> unidades com aviso.
            $table->decimal('peso_medio_kg', 10, 3)->nullable()->after('formato_unidade');
            $table->unsignedInteger('qtd_min')->default(1)->after('peso_medio_kg');
            $table->unsignedInteger('qtd_max')->nullable()->after('qtd_min');
            // Nomes por que os clientes tratam o produto no WhatsApp.
            $table->json('aliases')->nullable()->after('qtd_max');
            // Confirmado por uma pessoa: a partir dai o sync do site nao lhe toca.
            $table->boolean('unidades_confirmadas')->default(false)->after('aliases');
        });
    }

    public function down(): void
    {
        Schema::table('woo_products', function (Blueprint $table): void {
            $table->dropColumn([
                'unidade_venda',
                'formato_qtd',
                'formato_unidade',
                'peso_medio_kg',
                'qtd_min',
                'qtd_max',
                'aliases',
                'unidades_confirmadas',
            ]);
        });
    }
};
