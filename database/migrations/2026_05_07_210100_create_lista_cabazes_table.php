<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_cabazes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('semana_numero');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('descricao')->nullable();
            $table->enum('estado', ['rascunho', 'publicada'])->default('rascunho');
            $table->timestamps();
            $table->unique(['semana_numero', 'ano', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_cabazes');
    }
};
