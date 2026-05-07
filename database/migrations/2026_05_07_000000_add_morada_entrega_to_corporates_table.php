<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            if (! Schema::hasColumn('corporates', 'morada_entrega')) {
                $table->string('morada_entrega', 500)->nullable()->after('sucursal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            if (Schema::hasColumn('corporates', 'morada_entrega')) {
                $table->dropColumn('morada_entrega');
            }
        });
    }
};
