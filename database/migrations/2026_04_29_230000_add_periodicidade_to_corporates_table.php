<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            if (! Schema::hasColumn('corporates', 'periodicidade_entrega')) {
                $table->enum('periodicidade_entrega', ['semanal', 'quinzenal'])->default('semanal')->after('dias_entrega');
            }

            if (! Schema::hasColumn('corporates', 'quinzenal_referencia')) {
                $table->date('quinzenal_referencia')->nullable()->after('periodicidade_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            $table->dropColumn(['periodicidade_entrega', 'quinzenal_referencia']);
        });
    }
};
