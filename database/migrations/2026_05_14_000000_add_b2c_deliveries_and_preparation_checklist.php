<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atribuicoes', function (Blueprint $table) {
            if (! Schema::hasColumn('atribuicoes', 'tipo')) {
                $table->string('tipo')->default('corporate')->after('id');
            }

            if (! Schema::hasColumn('atribuicoes', 'woo_order_id')) {
                $table->foreignId('woo_order_id')->nullable()->after('corporate_id')->constrained('woo_orders')->cascadeOnDelete();
            }
        });

        Schema::table('registo_entregas', function (Blueprint $table) {
            if (! Schema::hasColumn('registo_entregas', 'tipo')) {
                $table->string('tipo')->default('corporate')->after('id');
            }

            if (! Schema::hasColumn('registo_entregas', 'woo_order_id')) {
                $table->foreignId('woo_order_id')->nullable()->after('corporate_id')->constrained('woo_orders')->cascadeOnDelete();
            }
        });

        Schema::table('preparacao_items', function (Blueprint $table) {
            if (! Schema::hasColumn('preparacao_items', 'produtos_picados')) {
                $table->json('produtos_picados')->nullable()->after('feito_por');
            }
        });

        $this->makeCorporateColumnsNullable();
    }

    public function down(): void
    {
        Schema::table('preparacao_items', function (Blueprint $table) {
            if (Schema::hasColumn('preparacao_items', 'produtos_picados')) {
                $table->dropColumn('produtos_picados');
            }
        });

        Schema::table('registo_entregas', function (Blueprint $table) {
            if (Schema::hasColumn('registo_entregas', 'woo_order_id')) {
                $table->dropConstrainedForeignId('woo_order_id');
            }

            if (Schema::hasColumn('registo_entregas', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });

        Schema::table('atribuicoes', function (Blueprint $table) {
            if (Schema::hasColumn('atribuicoes', 'woo_order_id')) {
                $table->dropConstrainedForeignId('woo_order_id');
            }

            if (Schema::hasColumn('atribuicoes', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }

    private function makeCorporateColumnsNullable(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE atribuicoes MODIFY corporate_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE registo_entregas MODIFY corporate_id BIGINT UNSIGNED NULL');

            return;
        }

        Schema::table('atribuicoes', function (Blueprint $table) {
            $table->foreignId('corporate_id')->nullable()->change();
        });

        Schema::table('registo_entregas', function (Blueprint $table) {
            $table->foreignId('corporate_id')->nullable()->change();
        });
    }
};
