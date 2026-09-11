<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->decimal('initial_order_total', 14, 2)->nullable()->after('budget_cap_enforced');
        });

        DB::table('inventory_orders')
            ->whereNull('initial_order_total')
            ->update([
                'initial_order_total' => DB::raw(
                    '(SELECT COALESCE(SUM(line_total), 0) FROM inventory_order_lines WHERE inventory_order_lines.inventory_order_id = inventory_orders.id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_orders', function (Blueprint $table) {
            $table->dropColumn('initial_order_total');
        });
    }
};
