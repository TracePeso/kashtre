<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_module_configs', 'enable_floor_stock_management')) {
                $table->boolean('enable_floor_stock_management')->default(true)->after('evaluation_committee_required');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'enable_crash_cart_management')) {
                $table->boolean('enable_crash_cart_management')->default(false)->after('enable_floor_stock_management');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'enable_batch_lot_tracking')) {
                $table->boolean('enable_batch_lot_tracking')->default(false)->after('enable_crash_cart_management');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'enable_serial_number_tracking')) {
                $table->boolean('enable_serial_number_tracking')->default(false)->after('enable_batch_lot_tracking');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_module_configs', function (Blueprint $table) {
            foreach ([
                'enable_floor_stock_management',
                'enable_crash_cart_management',
                'enable_batch_lot_tracking',
                'enable_serial_number_tracking',
            ] as $column) {
                if (Schema::hasColumn('inventory_module_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
