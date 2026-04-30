<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'preferences_text')) {
                $table->text('preferences_text')->nullable()->after('excluded_products');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (Schema::hasColumn('woo_orders', 'preferences_text')) {
                $table->dropColumn('preferences_text');
            }
        });
    }
};
