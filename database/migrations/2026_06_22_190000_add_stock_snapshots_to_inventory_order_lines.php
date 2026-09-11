<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->decimal('current_stock_suom', 14, 4)->default(0)->after('system_quantity_suom');
            $table->decimal('stock_days_at_order', 8, 1)->nullable()->after('current_stock_suom');
            $table->decimal('days_left_at_order', 8, 1)->nullable()->after('stock_days_at_order');
            $table->decimal('order_days', 10, 4)->nullable()->after('days_left_at_order');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'current_stock_suom',
                'stock_days_at_order',
                'days_left_at_order',
                'order_days',
            ]);
        });
    }
};
