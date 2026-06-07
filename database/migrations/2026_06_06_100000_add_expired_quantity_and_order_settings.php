<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->decimal('expired_quantity_suom', 14, 4)->default(0)->after('damaged_quantity_suom');
        });

        Schema::table('inventory_stock_count_lines', function (Blueprint $table) {
            $table->decimal('expired_quantity_suom', 14, 4)->default(0)->after('damaged_quantity_suom');
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->decimal('notification_to_order_days', 8, 2)->default(0)->after('buffer_stock_days');
            $table->decimal('period_of_order_days', 8, 2)->default(30)->after('notification_to_order_days');
        });

        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->decimal('period_of_order_days', 8, 2)->nullable()->after('moving_average_days');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn('period_of_order_days');
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            $table->dropColumn(['notification_to_order_days', 'period_of_order_days']);
        });

        Schema::table('inventory_stock_count_lines', function (Blueprint $table) {
            $table->dropColumn('expired_quantity_suom');
        });

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropColumn('expired_quantity_suom');
        });
    }
};
