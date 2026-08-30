<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('viaturas')) {
            return;
        }

        // As matriculas que aparecem na preparacao e vao para a guia de
        // transporte. Passaram a ser uma lista escolhida, em vez de texto
        // livre escrito a mao em cada linha.
        Schema::create('viaturas', function (Blueprint $table): void {
            $table->id();
            $table->string('matricula')->unique();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viaturas');
    }
};
