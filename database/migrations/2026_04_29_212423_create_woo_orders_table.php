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
        Schema::create('woo_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_id')->unique();
            $table->string('status');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('billing_name')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_email')->nullable();
            $table->json('line_items')->nullable();
            $table->date('postponed_until')->nullable();
            $table->json('excluded_products')->nullable();
            $table->string('dia_entrega')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_orders');
    }
};
