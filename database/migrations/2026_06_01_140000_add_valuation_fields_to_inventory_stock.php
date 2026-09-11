<?php

use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->decimal('weighted_avg_cost', 14, 2)->nullable()->after('last_purchase_price');
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->decimal('line_valuation', 14, 2)->nullable()->after('unit_price');
            $table->decimal('balance_valuation', 14, 2)->nullable()->after('line_valuation');
        });

        $this->recalculateValuations();
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropColumn(['line_valuation', 'balance_valuation']);
        });

        Schema::table('inventory_stock_levels', function (Blueprint $table) {
            $table->dropColumn('weighted_avg_cost');
        });
    }

    private function recalculateValuations(): void
    {
        $pairs = InventoryStockMovement::query()
            ->select('business_id', 'item_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $totalValue = 0.0;
            $balance = 0.0;
            $weightedAvg = 0.0;

            InventoryStockMovement::query()
                ->where('business_id', $pair->business_id)
                ->where('item_id', $pair->item_id)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->each(function (InventoryStockMovement $movement) use (&$totalValue, &$balance, &$weightedAvg) {
                    $delta = (float) $movement->quantity_delta;
                    $unitPrice = (float) ($movement->unit_price ?? 0);
                    $lineValuation = round(abs($delta) * $unitPrice, 2);

                    if ($delta > 0) {
                        $totalValue += $lineValuation;
                        $balance += $delta;
                        $weightedAvg = $balance > 0 ? round($totalValue / $balance, 2) : 0.0;
                    } elseif ($delta < 0) {
                        $totalValue = max(0, $totalValue + ($delta * $weightedAvg));
                        $balance += $delta;
                        $weightedAvg = $balance > 0 ? round($totalValue / $balance, 2) : 0.0;
                    }

                    $movement->update([
                        'line_valuation' => $lineValuation,
                        'balance_valuation' => round(max(0, $totalValue), 2),
                    ]);
                });

            InventoryStockLevel::query()
                ->where('business_id', $pair->business_id)
                ->where('item_id', $pair->item_id)
                ->update(['weighted_avg_cost' => $weightedAvg > 0 ? $weightedAvg : null]);
        }
    }
};
