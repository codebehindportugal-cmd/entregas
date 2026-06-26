<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('woo_orders', 'postponement_history')) {
                $table->json('postponement_history')->nullable()->after('postponed_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('woo_orders', 'postponement_history')) {
                $table->dropColumn('postponement_history');
            }
        });
    }
};
