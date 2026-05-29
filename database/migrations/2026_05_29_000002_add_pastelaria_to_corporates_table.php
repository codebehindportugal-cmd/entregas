<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'pastelaria')) {
                $table->json('pastelaria')->nullable()->after('frutas_por_dia');
            }

            if (! Schema::hasColumn('corporates', 'pastelaria_por_dia')) {
                $table->json('pastelaria_por_dia')->nullable()->after('pastelaria');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'pastelaria_por_dia')) {
                $table->dropColumn('pastelaria_por_dia');
            }

            if (Schema::hasColumn('corporates', 'pastelaria')) {
                $table->dropColumn('pastelaria');
            }
        });
    }
};
