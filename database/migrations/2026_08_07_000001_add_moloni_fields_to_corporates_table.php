<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'moloni_composto_ref')) {
                $table->string('moloni_composto_ref', 60)->nullable()->after('preco_cabaz');
            }
            if (! Schema::hasColumn('corporates', 'dias_vencimento')) {
                $table->unsignedSmallInteger('dias_vencimento')->nullable()->after('moloni_composto_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            foreach (['moloni_composto_ref', 'dias_vencimento'] as $coluna) {
                if (Schema::hasColumn('corporates', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
