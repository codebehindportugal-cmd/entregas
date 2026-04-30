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
        Schema::create('corporates', function (Blueprint $table) {
            $table->id();
            $table->string('empresa');
            $table->string('sucursal')->nullable();
            $table->json('dias_entrega');
            $table->string('horario_entrega')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_telefone')->nullable();
            $table->string('fatura_nome')->nullable();
            $table->string('fatura_nif')->nullable();
            $table->string('fatura_email')->nullable();
            $table->string('fatura_morada')->nullable();
            $table->unsignedInteger('numero_caixas')->default(1);
            $table->decimal('peso_total', 8, 2)->default(0);
            $table->json('frutas')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporates');
    }
};
