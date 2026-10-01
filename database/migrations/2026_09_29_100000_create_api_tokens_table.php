<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaves da API por utilizador (29/09/2026).
 *
 * Este projecto nao tem Sanctum e o composer.lock so se actualiza numa
 * maquina com PHP, por isso a tabela e propria e pequena: so o hash da chave
 * (sha256), nunca a chave. O CLAUDE_API_TOKEN do .env continua a valer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_tokens')) {
            return;
        }

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome')->default('api');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('ultimo_uso_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
