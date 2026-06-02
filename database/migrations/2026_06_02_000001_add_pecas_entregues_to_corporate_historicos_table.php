<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_historicos', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporate_historicos', 'pecas_entregues')) {
                $table->unsignedInteger('pecas_entregues')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporate_historicos', function (Blueprint $table): void {
            if (Schema::hasColumn('corporate_historicos', 'pecas_entregues')) {
                $table->dropColumn('pecas_entregues');
            }
        });
    }
};
