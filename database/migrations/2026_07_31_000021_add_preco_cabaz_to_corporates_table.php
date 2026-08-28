<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'preco_cabaz')) {
                $table->decimal('preco_cabaz', 10, 2)->nullable()->after('cabaz_quantidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'preco_cabaz')) {
                $table->dropColumn('preco_cabaz');
            }
        });
    }
};
