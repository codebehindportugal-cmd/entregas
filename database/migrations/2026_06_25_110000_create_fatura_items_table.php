<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('despesa_id')->constrained('despesas')->cascadeOnDelete();
            $table->string('descricao');
            $table->decimal('quantidade', 10, 3)->default(1);
            $table->decimal('preco_unitario', 10, 4);
            $table->decimal('iva_percentagem', 5, 2)->default(23);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_items');
    }
};
