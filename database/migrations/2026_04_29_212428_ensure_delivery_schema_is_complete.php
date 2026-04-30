<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migration protege o arranque caso alguma migration tenha sido marcada como executada enquanto ainda estava vazia.
        if (Schema::hasTable('corporates') && ! Schema::hasColumn('corporates', 'empresa')) {
            Schema::table('corporates', function (Blueprint $table) {
                $table->string('empresa')->after('id');
                $table->string('sucursal')->nullable()->after('empresa');
                $table->json('dias_entrega')->after('sucursal');
                $table->string('horario_entrega')->nullable()->after('dias_entrega');
                $table->string('responsavel_nome')->nullable()->after('horario_entrega');
                $table->string('responsavel_telefone')->nullable()->after('responsavel_nome');
                $table->string('fatura_nome')->nullable()->after('responsavel_telefone');
                $table->string('fatura_nif')->nullable()->after('fatura_nome');
                $table->string('fatura_email')->nullable()->after('fatura_nif');
                $table->string('fatura_morada')->nullable()->after('fatura_email');
                $table->unsignedInteger('numero_caixas')->default(1)->after('fatura_morada');
                $table->decimal('peso_total', 8, 2)->default(0)->after('numero_caixas');
                $table->json('frutas')->nullable()->after('peso_total');
                $table->text('notas')->nullable()->after('frutas');
                $table->boolean('ativo')->default(true)->after('notas');
            });
        }

        if (! Schema::hasTable('atribuicoes')) {
            Schema::create('atribuicoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('corporate_id')->constrained('corporates')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('dia_semana', ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta']);
                $table->timestamps();
                $table->unique(['corporate_id', 'dia_semana']);
            });
        }

        if (Schema::hasTable('registo_entregas') && ! Schema::hasColumn('registo_entregas', 'corporate_id')) {
            Schema::table('registo_entregas', function (Blueprint $table) {
                $table->foreignId('corporate_id')->after('id')->constrained('corporates')->cascadeOnDelete();
                $table->foreignId('user_id')->after('corporate_id')->constrained()->cascadeOnDelete();
                $table->date('data_entrega')->after('user_id');
                $table->enum('status', ['pendente', 'entregue', 'falhou'])->default('pendente')->after('data_entrega');
                $table->time('hora_entrega')->nullable()->after('status');
                $table->text('nota')->nullable()->after('hora_entrega');
                $table->json('fotos')->nullable()->after('nota');
                $table->unique(['corporate_id', 'user_id', 'data_entrega']);
            });
        }

        if (Schema::hasTable('woo_orders') && ! Schema::hasColumn('woo_orders', 'woo_id')) {
            Schema::table('woo_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('woo_id')->unique()->after('id');
                $table->string('status')->after('woo_id');
                $table->decimal('total', 10, 2)->default(0)->after('status');
                $table->string('billing_name')->nullable()->after('total');
                $table->string('billing_phone')->nullable()->after('billing_name');
                $table->string('billing_email')->nullable()->after('billing_phone');
                $table->json('line_items')->nullable()->after('billing_email');
                $table->date('postponed_until')->nullable()->after('line_items');
                $table->json('excluded_products')->nullable()->after('postponed_until');
                $table->string('dia_entrega')->nullable()->after('excluded_products');
                $table->json('raw_payload')->nullable()->after('dia_entrega');
                $table->timestamp('synced_at')->nullable()->after('raw_payload');
            });
        }

        if (Schema::hasTable('whats_app_logs') && ! Schema::hasColumn('whats_app_logs', 'to')) {
            Schema::table('whats_app_logs', function (Blueprint $table) {
                $table->string('to')->after('id');
                $table->text('message')->after('to');
                $table->string('status')->default('pending')->after('message');
                $table->json('response')->nullable()->after('status');
                $table->timestamp('sent_at')->nullable()->after('response');
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'key')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('key')->unique()->after('id');
                $table->text('value')->nullable()->after('key');
            });
        }
    }
};
