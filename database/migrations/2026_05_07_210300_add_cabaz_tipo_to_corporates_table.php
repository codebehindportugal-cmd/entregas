<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'cabaz_tipo')) {
                $table->string('cabaz_tipo')->nullable()->after('numero_caixas');
            }

            if (! Schema::hasColumn('corporates', 'cabaz_quantidade')) {
                $table->unsignedSmallInteger('cabaz_quantidade')->nullable()->after('cabaz_tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'cabaz_quantidade')) {
                $table->dropColumn('cabaz_quantidade');
            }

            if (Schema::hasColumn('corporates', 'cabaz_tipo')) {
                $table->dropColumn('cabaz_tipo');
            }
        });
    }
};
