<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryDemandLedger;
use App\Models\InventoryStockLevel;
use App\Models\Store;

class InventoryDaysOfStockService
{
    public const FORECAST_CONSUMPTION = 'consumption';

    public const FORECAST_DEMAND = 'demand';

    /**
     * Window selection matrix.
     */
    public function forecastWindowDays(float $requestedCoverageDays): int
    {
        return match (true) {
            $requestedCoverageDays < 15 => 15,
            $requestedCoverageDays <= 30 => 30,
            $requestedCoverageDays <= 90 => 90,
            $requestedCoverageDays <= 180 => 180,
            default => 365,
        };
    }

    public function movingAverageDaily(
        int $businessId,
        int $storeId,
        int $itemId,
        int $windowDays,
        string $basis = self::FORECAST_CONSUMPTION
    ): float {
        $from = now()->subDays($windowDays)->startOfDay();

        if ($basis === self::FORECAST_DEMAND) {
            $total = (float) InventoryDemandLedger::query()
                ->where('business_id', $businessId)
                ->where('store_id', $storeId)
                ->where('item_id', $itemId)
                ->where('occurred_at', '>=', $from)
                ->sum('quantity');

            return $windowDays > 0 ? round($total / $windowDays, 4) : 0.0;
        }

        $total = (float) InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->where('consumption_date', '>=', $from->toDateString())
            ->where('source', '!=', InventoryDailyConsumption::SOURCE_WASTAGE_EXPIRED)
            ->sum('quantity_suom');

        return $windowDays > 0 ? round($total / $windowDays, 4) : 0.0;
    }

    /**
     * Current stock days: always on-hand ÷ 15-day MA.
     * $coverageHint is accepted for call-site compatibility but ignored for display —
     * use forecastWindowDays() only when sizing order-generation windows.
     */
    public function currentStockDays(int $businessId, int $storeId, int $itemId, ?float $coverageHint = null): ?float
    {
        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->value('quantity_suom') ?? 0);

        unset($coverageHint);

        $ma = $this->movingAverageDaily($businessId, $storeId, $itemId, 15);

        if ($ma <= 0) {
            return null;
        }

        return round($onHand / $ma, 2);
    }

    /**
     * Suggested units to reach a coverage horizon from current on-hand.
     * $maxDaysOverride wins over the store profile (e.g. form coverage days).
     */
    public function suggestedUnitsToMax(
        Store $store,
        int $itemId,
        string $basis = self::FORECAST_CONSUMPTION,
        ?float $maxDaysOverride = null
    ): float {
        $maxDays = (float) ($maxDaysOverride ?? $store->max_stock_days ?? 0);
        if ($maxDays <= 0) {
            return 0;
        }

        $window = $this->forecastWindowDays($maxDays);
        $ma = $this->movingAverageDaily(
            (int) $store->business_id,
            (int) $store->id,
            $itemId,
            $window,
            $basis
        );

        if ($ma <= 0) {
            return 0;
        }

        $target = round($ma * $maxDays, 4);
        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $store->business_id)
            ->where('store_id', $store->id)
            ->where('item_id', $itemId)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->value('quantity_suom') ?? 0);

        return max(0, round($target - $onHand, 4));
    }
}
