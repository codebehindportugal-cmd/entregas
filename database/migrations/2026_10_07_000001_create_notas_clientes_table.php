<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notas fixas de cada cliente B2C ("nao gosta de kiwi", "deixar na
        // portaria"). Nao ha tabela de clientes — cada encomenda e um perfil —
        // por isso a nota fica presa ao telefone normalizado (ClientesB2c).
        Schema::create('notas_clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('telefone', 20)->unique();
            $table->string('nome')->nullable();
            $table->text('notas');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_clientes');
    }
};
