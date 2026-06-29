<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sazonalidade', function (Blueprint $table): void {
            $table->id();
            $table->string('produto');
            $table->string('categoria'); // fruta, legume, hortalica, outro
            $table->json('meses'); // [1, 2, 3, 11, 12]
            $table->string('notas')->nullable();
            $table->timestamps();
            $table->unique('produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sazonalidade');
    }
};
