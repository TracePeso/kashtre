<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
use App\Models\InventoryStockLevel;
use Illuminate\Support\Facades\DB;

class InventoryStockCountShrinkageService
{
    /** @var array<string, array<int, array{qty: float, ugx: float}>> */
    private array $pageCache = [];

    public function snapshotLineShrinkage(InventoryStockCountLine $line, float $unitCost): void
    {
        $unaccounted = $line->unaccountedLossSuom();
        $shrinkage = $line->totalShrinkageLossSuom();

        $line->update([
            'unaccounted_quantity_suom' => $unaccounted,
            'shrinkage_quantity_suom' => $shrinkage,
            'shrinkage_value_ugx' => round($shrinkage * max(0, $unitCost), 2),
        ]);
    }

    public function unitCostForLine(InventoryStockCount $count, InventoryStockCountLine $line): float
    {
        $stock = InventoryStockLevel::query()
            ->where('business_id', $count->business_id)
            ->where('store_id', $count->store_id)
            ->where('item_id', $line->item_id)
            ->first();

        if (! $stock) {
            return 0.0;
        }

        return (float) ($stock->weighted_avg_cost ?? $stock->last_purchase_price ?? 0);
    }

    /**
     * @param  array<int, int>  $storeIds
     * @param  array<int, int>  $itemIds
     */
    public function warmPageCumulativeShrinkage(int $businessId, array $storeIds, array $itemIds): void
    {
        if ($itemIds === [] || $storeIds === []) {
            return;
        }

        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        sort($storeIds);
        $cacheKey = $businessId.':'.implode(',', $storeIds);

        if (! isset($this->pageCache[$cacheKey])) {
            $this->pageCache[$cacheKey] = [];
        }

        $missingItemIds = array_values(array_diff(
            array_map('intval', $itemIds),
            array_keys($this->pageCache[$cacheKey])
        ));

        if ($missingItemIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($missingItemIds), '?'));
        $storePlaceholders = implode(',', array_fill(0, count($storeIds), '?'));

        $rows = DB::select(
            "SELECT scl.item_id,
                SUM(COALESCE(
                    scl.shrinkage_quantity_suom,
                    GREATEST(0, scl.system_quantity_suom - scl.physical_quantity_suom - scl.damaged_quantity_suom - COALESCE(scl.expired_quantity_suom, 0))
                    + scl.damaged_quantity_suom + COALESCE(scl.expired_quantity_suom, 0)
                )) as cumulative_qty,
                SUM(COALESCE(
                    scl.shrinkage_value_ugx,
                    (
                        GREATEST(0, scl.system_quantity_suom - scl.physical_quantity_suom - scl.damaged_quantity_suom - COALESCE(scl.expired_quantity_suom, 0))
                        + scl.damaged_quantity_suom + COALESCE(scl.expired_quantity_suom, 0)
                    ) * COALESCE(sl.weighted_avg_cost, sl.last_purchase_price, 0)
                )) as cumulative_ugx
            FROM inventory_stock_count_lines scl
            INNER JOIN inventory_stock_counts sc ON sc.id = scl.inventory_stock_count_id
            LEFT JOIN inventory_stock_levels sl
                ON sl.business_id = sc.business_id
                AND sl.store_id = sc.store_id
                AND sl.item_id = scl.item_id
            WHERE sc.business_id = ?
                AND sc.status = ?
                AND sc.store_id IN ({$storePlaceholders})
                AND scl.item_id IN ({$placeholders})
            GROUP BY scl.item_id",
            array_merge(
                [$businessId, InventoryStockCount::STATUS_APPROVED],
                $storeIds,
                $missingItemIds
            )
        );

        foreach ($missingItemIds as $itemId) {
            $this->pageCache[$cacheKey][$itemId] = ['qty' => 0.0, 'ugx' => 0.0];
        }

        foreach ($rows as $row) {
            $this->pageCache[$cacheKey][(int) $row->item_id] = [
                'qty' => (float) $row->cumulative_qty,
                'ugx' => (float) $row->cumulative_ugx,
            ];
        }
    }

    /**
     * @param  array<int, int>  $storeIds
     * @return array{qty: float, ugx: float}
     */
    public function cumulativeForItem(int $businessId, array $storeIds, int $itemId): array
    {
        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        sort($storeIds);
        $cacheKey = $businessId.':'.implode(',', $storeIds);

        return $this->pageCache[$cacheKey][$itemId] ?? ['qty' => 0.0, 'ugx' => 0.0];
    }
}
