<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('corporates', 'cp_entrega')) {
                $table->string('cp_entrega', 15)->nullable()->after('morada_entrega');
            }
            if (! Schema::hasColumn('corporates', 'cidade_entrega')) {
                $table->string('cidade_entrega', 120)->nullable()->after('cp_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table): void {
            foreach (['cp_entrega', 'cidade_entrega'] as $coluna) {
                if (Schema::hasColumn('corporates', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
