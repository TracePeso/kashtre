<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryMonthlyConsumption;
use Carbon\Carbon;

class InventoryMonthlyConsumptionService
{
    public function syncMonthFromDaily(int $businessId, int $storeId, int $itemId, string $date): void
    {
        $monthStart = Carbon::parse($date)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($date)->endOfMonth()->toDateString();

        $row = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereIn('source', InventoryDailyConsumption::demandSources())
            ->whereBetween('consumption_date', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(SUM(quantity_suom), 0) as total_quantity_suom')
            ->selectRaw('COUNT(DISTINCT consumption_date) as days_with_usage')
            ->first();

        $total = (float) ($row->total_quantity_suom ?? 0);

        if ($total <= 0) {
            InventoryMonthlyConsumption::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('item_id', $itemId)
                ->whereDate('consumption_month', $monthStart)
                ->delete();

            return;
        }

        InventoryMonthlyConsumption::query()->updateOrCreate(
            [
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
                'consumption_month' => $monthStart,
            ],
            [
                'total_quantity_suom' => $total,
                'days_with_usage' => (int) ($row->days_with_usage ?? 0),
            ]
        );
    }
}
