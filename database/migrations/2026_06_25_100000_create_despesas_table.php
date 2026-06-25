<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->string('numero_fatura')->nullable();
            $table->string('fornecedor')->nullable();
            $table->decimal('valor', 10, 2);
            $table->date('data');
            $table->string('categoria')->default('outro');
            $table->string('marca')->default('horta_da_maria');
            $table->string('ficheiro_path')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
    }
};
