<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_cabaz_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lista_cabaz_id')->constrained('lista_cabazes')->cascadeOnDelete();
            $table->enum('cabaz_tipo', ['mini', 'pequeno', 'medio', 'grande']);
            $table->string('produto');
            $table->string('categoria')->nullable();
            $table->decimal('quantidade', 8, 3);
            $table->string('unidade', 20)->default('un');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_cabaz_itens');
    }
};
