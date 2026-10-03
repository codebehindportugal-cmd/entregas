<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Caixa de pedidos: as encomendas que chegam por email ou WhatsApp ficam
        // aqui ate alguem as confirmar. So depois disso vao para o WooCommerce.
        Schema::create('pedidos_recebidos', function (Blueprint $table): void {
            $table->id();
            $table->string('canal', 20); // email | whatsapp | manual
            // Id da mensagem na origem (Gmail ou WhatsApp). Impede o mesmo email de entrar duas vezes.
            $table->string('origem_id', 191)->nullable();
            $table->string('remetente')->nullable();
            $table->string('assunto')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->longText('texto_original');

            // O pedido no formato do /api/v1/encomendas/validar, preparado pelo Claude.
            $table->json('pedido')->nullable();
            // O que o validador devolveu da ultima vez (cliente, linhas, total).
            $table->json('resumo')->nullable();
            $table->json('avisos')->nullable();
            $table->json('erros')->nullable();
            // Perguntas que o Claude deixou para quem confirma (texto livre).
            $table->json('duvidas')->nullable();

            $table->string('estado', 20)->default('novo');
            $table->foreignId('woo_order_id')->nullable()->constrained('woo_orders')->nullOnDelete();
            $table->foreignId('tratado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tratado_em')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['canal', 'origem_id']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_recebidos');
    }
};
