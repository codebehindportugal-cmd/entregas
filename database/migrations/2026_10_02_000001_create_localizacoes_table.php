<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Onde fica cada morada de entrega (empresas e B2C), procurada uma vez
        // no OpenStreetMap e guardada, para organizar as voltas pela distancia
        // real em vez do centro do codigo postal. Se a morada mudar, a chave
        // muda e procura-se outra vez.
        Schema::create('localizacoes', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 40)->unique();
            $table->text('morada')->nullable();
            $table->string('cp', 20)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            // morada | codigo_postal | falhou
            $table->string('fonte', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localizacoes');
    }
};
