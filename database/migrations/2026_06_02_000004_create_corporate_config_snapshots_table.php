<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_config_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('corporate_id')->constrained('corporates')->cascadeOnDelete();
            $table->date('effective_from');
            $table->json('dados');
            $table->timestamps();

            $table->unique(['corporate_id', 'effective_from'], 'corporate_config_effective_unique');
            $table->index(['corporate_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_config_snapshots');
    }
};
