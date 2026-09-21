<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atribuicoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('atribuicoes', 'ordem')) {
                $table->unsignedInteger('ordem')->nullable()->after('dia_semana');
            }
        });
    }

    public function down(): void
    {
        Schema::table('atribuicoes', function (Blueprint $table): void {
            if (Schema::hasColumn('atribuicoes', 'ordem')) {
                $table->dropColumn('ordem');
            }
        });
    }
};
