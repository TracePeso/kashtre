<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->decimal('peak_period_percent', 8, 4)
                ->nullable()
                ->default(0)
                ->after('period_of_order_days');
        });

        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->decimal('base_suggested_quantity_suom', 14, 4)
                ->nullable()
                ->after('system_quantity_suom');

            $table->decimal('peak_consumption_increase_percent', 8, 4)
                ->nullable()
                ->default(0)
                ->after('base_suggested_quantity_suom');

            $table->decimal('peak_impact_percent', 8, 4)
                ->nullable()
                ->default(0)
                ->after('peak_consumption_increase_percent');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'base_suggested_quantity_suom',
                'peak_consumption_increase_percent',
                'peak_impact_percent',
            ]);
        });

        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn('peak_period_percent');
        });
    }
};
