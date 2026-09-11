<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_count_lines', function (Blueprint $table) {
            $table->decimal('unaccounted_quantity_suom', 14, 4)->nullable()->after('expired_quantity_suom');
            $table->decimal('shrinkage_quantity_suom', 14, 4)->nullable()->after('unaccounted_quantity_suom');
            $table->decimal('shrinkage_value_ugx', 14, 2)->nullable()->after('shrinkage_quantity_suom');
        });

        $this->backfillApprovedLineShrinkage();
    }

    public function down(): void
    {
        Schema::table('inventory_stock_count_lines', function (Blueprint $table) {
            $table->dropColumn([
                'unaccounted_quantity_suom',
                'shrinkage_quantity_suom',
                'shrinkage_value_ugx',
            ]);
        });
    }

    private function backfillApprovedLineShrinkage(): void
    {
        $lines = DB::table('inventory_stock_count_lines as scl')
            ->join('inventory_stock_counts as sc', 'sc.id', '=', 'scl.inventory_stock_count_id')
            ->where('sc.status', 'approved')
            ->select([
                'scl.id',
                'scl.system_quantity_suom',
                'scl.physical_quantity_suom',
                'scl.damaged_quantity_suom',
                'scl.expired_quantity_suom',
                'sc.business_id',
                'sc.store_id',
                'scl.item_id',
            ])
            ->get();

        foreach ($lines as $line) {
            $verified = (float) $line->damaged_quantity_suom + (float) ($line->expired_quantity_suom ?? 0);
            $unaccounted = max(0, round(
                (float) $line->system_quantity_suom
                - (float) $line->physical_quantity_suom
                - $verified,
                4
            ));
            $shrinkage = round($unaccounted + $verified, 4);

            $unitCost = (float) (DB::table('inventory_stock_levels')
                ->where('business_id', $line->business_id)
                ->where('store_id', $line->store_id)
                ->where('item_id', $line->item_id)
                ->value(DB::raw('COALESCE(weighted_avg_cost, last_purchase_price, 0)')) ?? 0);

            DB::table('inventory_stock_count_lines')
                ->where('id', $line->id)
                ->update([
                    'unaccounted_quantity_suom' => $unaccounted,
                    'shrinkage_quantity_suom' => $shrinkage,
                    'shrinkage_value_ugx' => round($shrinkage * $unitCost, 2),
                ]);
        }
    }
};
