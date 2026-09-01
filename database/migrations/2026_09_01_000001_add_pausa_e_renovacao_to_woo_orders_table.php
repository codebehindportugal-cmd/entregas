<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('woo_orders', 'renovacao_automatica')) {
                // Subscricao auto-renovavel: no fim das entregas do ciclo a app
                // cria sozinha a encomenda de renovacao no WooCommerce.
                $table->boolean('renovacao_automatica')->default(false)->after('subscription_ends_at');
            }

            if (! Schema::hasColumn('woo_orders', 'pausada_em')) {
                // Janela de pausa. As entregas que caem la dentro nao acontecem
                // e as restantes empurram-se para a frente (o total mantem-se).
                $table->date('pausada_em')->nullable()->after('renovacao_automatica');
            }

            if (! Schema::hasColumn('woo_orders', 'pausada_ate')) {
                // Ultimo dia da pausa. Vazio = pausada por tempo indeterminado.
                $table->date('pausada_ate')->nullable()->after('pausada_em');
            }

            if (! Schema::hasColumn('woo_orders', 'renovada_em')) {
                // Quando a renovacao foi criada (so acontece uma vez por ciclo).
                $table->date('renovada_em')->nullable()->after('pausada_ate');
            }

            if (! Schema::hasColumn('woo_orders', 'renovacao_woo_order_id')) {
                // A encomenda nova que ficou a espera de pagamento.
                $table->unsignedBigInteger('renovacao_woo_order_id')->nullable()->after('renovada_em');
            }

            if (! Schema::hasColumn('woo_orders', 'renovacao_enviada_em')) {
                // Quando se enviou o link de pagamento ao cliente pelo WhatsApp.
                $table->timestamp('renovacao_enviada_em')->nullable()->after('renovacao_woo_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            foreach ([
                'renovacao_enviada_em',
                'renovacao_woo_order_id',
                'renovada_em',
                'pausada_ate',
                'pausada_em',
                'renovacao_automatica',
            ] as $coluna) {
                if (Schema::hasColumn('woo_orders', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
