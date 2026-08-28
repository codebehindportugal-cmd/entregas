<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'moloni_guia_ref')) {
                $table->string('moloni_guia_ref', 60)->nullable()->after('moloni_composto_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('corporates', 'moloni_guia_ref')) {
                $table->dropColumn('moloni_guia_ref');
            }
        });
    }
};
