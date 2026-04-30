<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'colaborador'])->default('colaborador')->after('password');
            $table->string('cor', 7)->default('#22C55E')->after('role');
            $table->boolean('ativo')->default(true)->after('cor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'cor', 'ativo']);
        });
    }
};
