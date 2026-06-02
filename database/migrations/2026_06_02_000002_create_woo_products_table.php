<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('woo_id')->unique();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('sku')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->string('permalink')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('price', 10, 4)->nullable();
            $table->decimal('regular_price', 10, 4)->nullable();
            $table->decimal('sale_price', 10, 4)->nullable();
            $table->string('stock_status')->nullable();
            $table->boolean('purchasable')->default(false);
            $table->boolean('em_epoca')->default(true);
            $table->boolean('disponivel_compra')->default(true);
            $table->string('epoca')->nullable();
            $table->foreignId('tabela_preco_item_id')->nullable()->constrained('tabela_preco_itens')->nullOnDelete();
            $table->decimal('custo_quantidade', 10, 4)->default(1);
            $table->string('custo_unidade', 20)->default('kg');
            $table->json('categories')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_products');
    }
};
