<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_preco_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('produto')->unique();
            $table->foreignId('tabela_preco_item_id')->nullable()->constrained('tabela_preco_itens')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_preco_mappings');
    }
};
