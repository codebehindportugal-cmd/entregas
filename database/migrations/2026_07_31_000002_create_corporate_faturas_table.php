<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_faturas', function (Blueprint $table): void {
            $table->id();
            $table->string('nif')->nullable();          // contribuinte agrupador
            $table->string('nome')->nullable();         // nome de faturacao usado
            $table->string('periodo', 7);               // YYYY-MM
            $table->unsignedBigInteger('document_id');  // id do documento Moloni
            $table->string('tipo')->default('fatura');
            $table->decimal('total', 10, 2)->nullable();
            $table->json('corporate_ids')->nullable();  // sucursais agrupadas nesta fatura
            $table->json('itens')->nullable();          // linhas/composicao faturadas
            $table->timestamp('emitida_em')->nullable();
            $table->timestamps();

            $table->index(['nif', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_faturas');
    }
};
