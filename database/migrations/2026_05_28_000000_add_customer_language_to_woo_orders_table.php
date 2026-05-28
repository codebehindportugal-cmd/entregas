<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('woo_orders', 'customer_language')) {
                $table->string('customer_language', 10)->nullable()->after('billing_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('woo_orders', 'customer_language')) {
                $table->dropColumn('customer_language');
            }
        });
    }
};
