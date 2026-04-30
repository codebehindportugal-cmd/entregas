<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'source_type')) {
                $table->string('source_type')->default('order')->after('woo_id');
            }

            if (! Schema::hasColumn('woo_orders', 'next_payment_at')) {
                $table->date('next_payment_at')->nullable()->after('postponed_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'next_payment_at']);
        });
    }
};
