<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('woo_orders', 'ordered_at')) {
                $table->timestamp('ordered_at')->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('woo_orders', 'scheduled_delivery_at')) {
                $table->date('scheduled_delivery_at')->nullable()->after('dia_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->dropColumn(['ordered_at', 'scheduled_delivery_at']);
        });
    }
};
