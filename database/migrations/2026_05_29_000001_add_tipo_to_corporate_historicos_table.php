<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_historicos', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporate_historicos', 'tipo')) {
                $table->string('tipo')->nullable()->default('nota')->after('data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporate_historicos', function (Blueprint $table): void {
            if (Schema::hasColumn('corporate_historicos', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
