<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'first_delivery_at')) {
                $table->date('first_delivery_at')->nullable()->after('next_payment_at');
            }

            if (! Schema::hasColumn('woo_orders', 'delivery_dates')) {
                $table->json('delivery_dates')->nullable()->after('first_delivery_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->dropColumn(['first_delivery_at', 'delivery_dates']);
        });
    }
};
