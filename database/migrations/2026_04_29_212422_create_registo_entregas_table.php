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
        Schema::create('registo_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_id')->constrained('corporates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('data_entrega');
            $table->enum('status', ['pendente', 'entregue', 'falhou'])->default('pendente');
            $table->time('hora_entrega')->nullable();
            $table->text('nota')->nullable();
            $table->json('fotos')->nullable();
            $table->timestamps();

            $table->unique(['corporate_id', 'user_id', 'data_entrega']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registo_entregas');
    }
};
