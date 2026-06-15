<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\Store;
use Illuminate\Support\Collection;

class InventoryNetworkRollupService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rollupForStore(int $businessId, int $storeId): array
    {
        $storeIds = Store::descendantIds($storeId);

        $levels = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->with(['item.itemUnit'])
            ->get();

        /** @var Collection<int, Collection<int, InventoryStockLevel>> $byItem */
        $byItem = $levels->groupBy('item_id');

        $rows = [];

        foreach ($byItem as $itemId => $itemLevels) {
            $first = $itemLevels->first();
            $system = $itemLevels->sum(fn (InventoryStockLevel $l) => (float) $l->quantity_suom);
            $physical = $itemLevels->contains(fn (InventoryStockLevel $l) => $l->physical_quantity_suom !== null)
                ? $itemLevels->sum(fn (InventoryStockLevel $l) => (float) ($l->physical_quantity_suom ?? $l->quantity_suom))
                : null;
            $damaged = $itemLevels->sum(fn (InventoryStockLevel $l) => (float) ($l->damaged_quantity_suom ?? 0));
            $expired = $itemLevels->sum(fn (InventoryStockLevel $l) => (float) ($l->expired_quantity_suom ?? 0));
            $usable = max(0, round(($physical ?? $system) - $damaged - $expired, 4));

            $rows[] = [
                'item_id' => (int) $itemId,
                'item' => $first?->item,
                'store_count' => $itemLevels->pluck('store_id')->unique()->count(),
                'system_quantity_suom' => round($system, 4),
                'physical_quantity_suom' => $physical !== null ? round($physical, 4) : null,
                'usable_quantity_suom' => $usable,
                'damaged_quantity_suom' => round($damaged, 4),
                'expired_quantity_suom' => round($expired, 4),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['item']->name ?? '', $b['item']->name ?? ''));

        return $rows;
    }
}
