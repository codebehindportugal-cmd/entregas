<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas longe (Algarve, Madeira, Braganca...) sao servidas por empresas
 * locais com fruta propria (Andre, 01/10/2026): nao entram nas voltas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            $table->boolean('parceiro_local')->default(false)->after('transportador');
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            $table->dropColumn('parceiro_local');
        });
    }
};
