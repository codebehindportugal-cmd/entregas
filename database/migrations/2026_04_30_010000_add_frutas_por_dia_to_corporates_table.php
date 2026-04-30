<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            if (! Schema::hasColumn('corporates', 'frutas_por_dia')) {
                $table->json('frutas_por_dia')->nullable()->after('frutas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            if (Schema::hasColumn('corporates', 'frutas_por_dia')) {
                $table->dropColumn('frutas_por_dia');
            }
        });
    }
};
