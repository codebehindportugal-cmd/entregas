<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('despesa_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, done, failed
            $table->string('image_path');
            $table->text('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
