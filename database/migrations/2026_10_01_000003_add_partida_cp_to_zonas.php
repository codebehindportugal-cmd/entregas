<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table): void {
            // Onde comeca a volta desta zona (codigo postal). Vazio: o armazem
            // nas Caldas. A zona do Porto comeca no Porto (o Valter leva-lhe a
            // mercadoria).
            $table->string('partida_cp', 20)->nullable()->after('codigos_postais');
        });

        \Illuminate\Support\Facades\DB::table('zonas')->where('nome', 'Zona do Porto')->whereNull('partida_cp')->update(['partida_cp' => '4470']);
    }

    public function down(): void
    {
        Schema::table('zonas', function (Blueprint $table): void {
            $table->dropColumn('partida_cp');
        });
    }
};
