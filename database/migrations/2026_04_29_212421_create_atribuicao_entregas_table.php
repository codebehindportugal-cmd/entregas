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
        Schema::create('atribuicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_id')->constrained('corporates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('dia_semana', ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta']);
            $table->timestamps();

            $table->unique(['corporate_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atribuicoes');
    }
};
