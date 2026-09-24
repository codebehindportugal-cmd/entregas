<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debitos diretos SEPA (Gestao -> Debitos SEPA).
 *
 * sepa_mandatos  — um por cliente que paga por debito direto (mandato assinado).
 * sepa_cobrancas — cada ficheiro pain.008 gerado. O banco so aceita UM pedido por
 *                  mes por cliente, dai o unique (sepa_mandato_id, mes_cobranca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepa_mandatos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_devedor', 70);
            $table->string('nif', 20)->nullable();
            $table->string('iban', 34);
            $table->string('bic', 11)->nullable();
            $table->string('mandato_ref', 35);            // MndtId (normalmente o NIF)
            $table->date('data_assinatura');               // DtOfSgntr
            $table->decimal('valor_mensal', 10, 2);
            $table->string('ultimo_mes_cobrado', 7)->nullable(); // YYYY-MM
            $table->unsignedTinyInteger('max_meses_por_cobranca')->default(2);
            $table->unsignedTinyInteger('dia_cobranca')->default(5);
            $table->boolean('ativo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique('mandato_ref');
        });

        Schema::create('sepa_cobrancas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sepa_mandato_id')->constrained('sepa_mandatos')->restrictOnDelete();
            $table->string('msg_id', 35)->unique();
            $table->string('end_to_end_id', 35);
            $table->date('data_cobranca');
            $table->string('mes_cobranca', 7);             // YYYY-MM da data de cobranca
            $table->string('mes_inicio', 7);               // primeiro mes cobrado
            $table->string('mes_fim', 7);                  // ultimo mes cobrado
            $table->unsignedTinyInteger('n_meses');
            $table->decimal('valor', 10, 2);
            $table->string('descricao', 140);
            $table->string('ultimo_mes_anterior', 7)->nullable(); // para desfazer
            $table->longText('xml');
            $table->string('gerado_por')->nullable();
            $table->timestamps();

            $table->unique(['sepa_mandato_id', 'mes_cobranca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepa_cobrancas');
        Schema::dropIfExists('sepa_mandatos');
    }
};
