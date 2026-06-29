<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabaz_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('cabaz_tipo'); // mini, pequeno, medio, grande
            $table->string('categoria'); // fruta, legume, hortalica, outro
            $table->unsignedSmallInteger('quantidade_itens')->default(1); // nr de produtos distintos desta categoria
            $table->decimal('quantidade_por_item', 8, 3)->default(1); // qtd de cada produto (ex: 1 un, 0.5 kg)
            $table->string('unidade', 20)->default('un');
            $table->decimal('peso_unitario_kg', 6, 3)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->unique(['cabaz_tipo', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabaz_templates');
    }
};
