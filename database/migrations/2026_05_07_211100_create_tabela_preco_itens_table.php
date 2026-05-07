<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabela_preco_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tabela_preco_id')->constrained('tabelas_precos')->cascadeOnDelete();
            $table->string('categoria');
            $table->string('produto');
            $table->string('origem')->nullable();
            $table->string('calibre')->nullable();
            $table->decimal('preco_kg', 8, 4);
            $table->decimal('preco_kg_iva', 8, 4);
            $table->string('unidade', 20)->default('kg');
            $table->text('notas')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabela_preco_itens');
    }
};
