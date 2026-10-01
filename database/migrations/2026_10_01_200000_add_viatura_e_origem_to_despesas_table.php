<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despesas por viatura e despesas que chegam de fora (01/10/2026).
 *
 * - viatura_id: combustivel, portagens e reparacoes ligados ao carro, para
 *   se ver quanto custa cada viatura.
 * - origem / origem_ref: de onde veio a despesa (ex.: "gestao.ateneya.com" e o
 *   id do documento la). E o que impede o mesmo documento de entrar duas
 *   vezes quando nao tem numero de fatura — os recibos de vencimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table): void {
            $table->foreignId('viatura_id')->nullable()->after('categoria')
                ->constrained('viaturas')->nullOnDelete();
            $table->string('origem', 50)->nullable()->after('notas');
            $table->string('origem_ref', 100)->nullable()->after('origem');
            $table->unique(['origem', 'origem_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table): void {
            $table->dropUnique(['origem', 'origem_ref']);
            $table->dropConstrainedForeignId('viatura_id');
            $table->dropColumn(['origem', 'origem_ref']);
        });
    }
};
