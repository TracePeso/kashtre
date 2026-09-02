<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_orders', 'forecast_basis')) {
                $table->string('forecast_basis', 32)
                    ->default('consumption')
                    ->after('budget_mode');
            }
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_module_configs', 'enable_internal_ordering')) {
                $table->boolean('enable_internal_ordering')->default(true)->after('enable_serial_number_tracking');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'enable_automated_stock_counts')) {
                $table->boolean('enable_automated_stock_counts')->default(true)->after('enable_internal_ordering');
            }
            if (! Schema::hasColumn('inventory_module_configs', 'enable_multi_store_network')) {
                $table->boolean('enable_multi_store_network')->default(true)->after('enable_automated_stock_counts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_orders', 'forecast_basis')) {
                $table->dropColumn('forecast_basis');
            }
        });

        Schema::table('inventory_module_configs', function (Blueprint $table) {
            foreach ([
                'enable_internal_ordering',
                'enable_automated_stock_counts',
                'enable_multi_store_network',
            ] as $column) {
                if (Schema::hasColumn('inventory_module_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
