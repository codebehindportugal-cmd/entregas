<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesa_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('despesa_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesa_fotos');
    }
};
