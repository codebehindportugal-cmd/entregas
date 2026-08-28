<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparacao_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('preparacao_items', 'matricula')) {
                $table->string('matricula', 20)->nullable()->after('produtos_picados');
            }
            if (! Schema::hasColumn('preparacao_items', 'guia_document_id')) {
                $table->unsignedBigInteger('guia_document_id')->nullable()->after('matricula');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preparacao_items', function (Blueprint $table): void {
            foreach (['matricula', 'guia_document_id'] as $coluna) {
                if (Schema::hasColumn('preparacao_items', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
