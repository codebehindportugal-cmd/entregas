<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'profile_preferences')) {
                $table->text('profile_preferences')->nullable()->after('preferences_text');
            }

            if (! Schema::hasColumn('woo_orders', 'customer_notes')) {
                $table->text('customer_notes')->nullable()->after('profile_preferences');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (Schema::hasColumn('woo_orders', 'profile_preferences')) {
                $table->dropColumn('profile_preferences');
            }

            if (Schema::hasColumn('woo_orders', 'customer_notes')) {
                $table->dropColumn('customer_notes');
            }
        });
    }
};
