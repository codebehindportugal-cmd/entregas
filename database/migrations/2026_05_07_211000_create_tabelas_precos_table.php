<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabelas_precos', function (Blueprint $table): void {
            $table->id();
            $table->string('fornecedor')->default('Sentido da Fruta');
            $table->string('descricao')->nullable();
            $table->date('valida_de');
            $table->date('valida_ate')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabelas_precos');
    }
};
