<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preparacao_items', function (Blueprint $table) {
            $table->id();
            $table->date('data_preparacao');
            $table->enum('tipo', ['corporate', 'b2c']);
            $table->foreignId('corporate_id')->nullable()->constrained('corporates')->cascadeOnDelete();
            $table->foreignId('woo_order_id')->nullable()->constrained('woo_orders')->cascadeOnDelete();
            $table->boolean('feito')->default(false);
            $table->timestamp('feito_at')->nullable();
            $table->foreignId('feito_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['data_preparacao', 'tipo', 'corporate_id'], 'prep_corporate_unique');
            $table->unique(['data_preparacao', 'tipo', 'woo_order_id'], 'prep_b2c_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preparacao_items');
    }
};
