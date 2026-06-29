<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_products', function (Blueprint $table): void {
            $table->json('images')->nullable()->after('image_url');
            $table->text('description')->nullable()->after('images');
            $table->text('short_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('woo_products', function (Blueprint $table): void {
            $table->dropColumn(['images', 'description', 'short_description']);
        });
    }
};
