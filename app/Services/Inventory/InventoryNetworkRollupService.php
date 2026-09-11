<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockLevel;
use App\Models\Item;
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

        $aggregates = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->selectRaw('item_id,
                SUM(quantity_suom) as system_quantity_suom,
                SUM(COALESCE(damaged_quantity_suom, 0)) as damaged_quantity_suom,
                SUM(COALESCE(expired_quantity_suom, 0)) as expired_quantity_suom,
                COUNT(DISTINCT store_id) as store_count')
            ->groupBy('item_id')
            ->get();

        if ($aggregates->isEmpty()) {
            return [];
        }

        /** @var Collection<int, Item> $items */
        $items = Item::query()
            ->whereIn('id', $aggregates->pluck('item_id'))
            ->with('itemUnit')
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($aggregates as $row) {
            $system = (float) $row->system_quantity_suom;
            $physical = $system;
            $damaged = (float) $row->damaged_quantity_suom;
            $expired = (float) $row->expired_quantity_suom;

            $rows[] = [
                'item_id' => (int) $row->item_id,
                'item' => $items->get((int) $row->item_id),
                'store_count' => (int) $row->store_count,
                'system_quantity_suom' => round($system, 4),
                'physical_quantity_suom' => round($physical, 4),
                'damaged_quantity_suom' => round($damaged, 4),
                'expired_quantity_suom' => round($expired, 4),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['item']->name ?? '', $b['item']->name ?? ''));

        return $rows;
    }
}
