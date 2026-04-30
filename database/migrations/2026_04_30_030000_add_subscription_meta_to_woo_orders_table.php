<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'cancelled_delivery_dates')) {
                $table->json('cancelled_delivery_dates')->nullable()->after('delivery_dates');
            }

            if (! Schema::hasColumn('woo_orders', 'subscription_ends_at')) {
                $table->date('subscription_ends_at')->nullable()->after('cancelled_delivery_dates');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (Schema::hasColumn('woo_orders', 'cancelled_delivery_dates')) {
                $table->dropColumn('cancelled_delivery_dates');
            }

            if (Schema::hasColumn('woo_orders', 'subscription_ends_at')) {
                $table->dropColumn('subscription_ends_at');
            }
        });
    }
};
