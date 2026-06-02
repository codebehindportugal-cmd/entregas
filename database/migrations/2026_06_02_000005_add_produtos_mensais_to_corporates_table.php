<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'produtos_mensais')) {
                $table->json('produtos_mensais')->nullable()->after('pastelaria_por_dia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'produtos_mensais')) {
                $table->dropColumn('produtos_mensais');
            }
        });
    }
};
