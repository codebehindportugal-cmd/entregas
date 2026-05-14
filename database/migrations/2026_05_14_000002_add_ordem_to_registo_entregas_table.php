<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registo_entregas', function (Blueprint $table): void {
            if (! Schema::hasColumn('registo_entregas', 'ordem')) {
                $table->unsignedInteger('ordem')->nullable()->after('data_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registo_entregas', function (Blueprint $table): void {
            if (Schema::hasColumn('registo_entregas', 'ordem')) {
                $table->dropColumn('ordem');
            }
        });
    }
};
