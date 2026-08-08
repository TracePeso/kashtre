<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryConsumptionEvent;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use App\Models\Item;
use App\Services\FinancialYearService;
use App\Support\StoreItemPairQuery;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryStockAnalyticsService
{
    /** @var array<int, string> */
    public const MOVING_AVERAGE_WINDOWS = [
        15 => 'ma_15_days',
        30 => 'ma_30_days',
        90 => 'ma_90_days',
        180 => 'ma_180_days',
        360 => 'ma_360_days',
    ];

    public function recalculateForStockLevel(InventoryStockLevel $stock): InventoryStockLevel
    {
        $businessId = (int) $stock->business_id;
        $storeId = (int) $stock->store_id;
        $itemId = (int) $stock->item_id;

        $updates = [];

        foreach (self::MOVING_AVERAGE_WINDOWS as $days => $column) {
            $updates[$column] = $this->movingAverage($businessId, $storeId, $itemId, $days);
        }

        $updates['daily_usage_suom'] = $updates['ma_30_days'];

        $stock->fill($updates)->save();

        return $stock->fresh();
    }

    public function movingAverage(int $businessId, int $storeId, int $itemId, int $days): float
    {
        $from = Carbon::today()->subDays($days - 1);

        $total = (float) InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereIn('source', InventoryDailyConsumption::demandSources())
            ->whereDate('consumption_date', '>=', $from)
            ->sum('quantity_suom');

        return round($total / max(1, $days), 4);
    }

    public function effectiveDailyUsage(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): float
    {
        $usage = (float) ($stock->daily_usage_suom ?? 0);

        if ($usage > 0) {
            return $usage;
        }

        if ($config) {
            return $config->effectiveDailyUsageSuom(null);
        }

        return 0.0;
    }

    /**
     * Excel column V / AA: 15-day moving average, or fixed daily average when V is zero.
     */
    public function excelDailyUsageSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): float
    {
        $ma15 = $this->movingAverageForStock($stock, 15);

        if ($ma15 > 0) {
            return $ma15;
        }

        return (float) ($config?->fixed_daily_average_suom ?? 0);
    }

    public function financialYearStart(int $businessId, ?Carbon $asOf = null): Carbon
    {
        return app(FinancialYearService::class)->periodStart($businessId, $asOf);
    }

    public function movementSumSince(InventoryStockLevel $stock, ?Carbon $since): float
    {
        $query = InventoryStockMovement::query()
            ->where('business_id', $stock->business_id)
            ->where('store_id', $stock->store_id)
            ->where('item_id', $stock->item_id);

        if ($since !== null) {
            $query->where('occurred_at', '>=', $since);
        }

        return (float) $query->sum('quantity_delta');
    }

    /**
     * Excel column AR: FY opening + purchases − sales + returns/transfers since FY start.
     */
    public function systemStockArSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): float
    {
        $fyStart = $this->financialYearStart((int) $stock->business_id);
        $opening = $this->openingQuantityAtFinancialYear($stock, $fyStart);

        return max(0, round($opening + $this->movementSumSince($stock, $fyStart), 4));
    }

    /**
     * Excel column M: physical count (AS) + purchases − sales + transfers since last count.
     * Falls back to on-hand ledger when no stock count snapshot exists.
     */
    public function currentStockLevelSuom(InventoryStockLevel $stock, ?float $movementSinceCount = null): float
    {
        $ledger = max(0, round((float) $stock->quantity_suom, 4));

        if ($stock->physical_counted_at === null) {
            return $ledger;
        }

        $as = (float) ($stock->physical_quantity_suom ?? 0);
        $since = Carbon::parse($stock->physical_counted_at)->startOfDay();
        $delta = $movementSinceCount ?? $this->movementSumSince($stock, $since);
        $fromCount = max(0, round($as + $delta, 4));

        // Legacy rows kept AS synced with the ledger on every movement.
        if (abs($as - $ledger) < 0.0001) {
            return $ledger;
        }

        return $fromCount;
    }

    /**
     * Excel column N: M ÷ (V or AA if V = 0).
     */
    public function stockDaysReport(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): ?float
    {
        $usage = $this->excelDailyUsageSuom($stock, $config);

        if ($usage <= 0) {
            return null;
        }

        return round($this->currentStockLevelSuom($stock) / $usage, 1);
    }

    /**
     * Excel columns AC / AE: safety and buffer stock using 15-day average (V or AA).
     */
    public function safetyStockSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config, ?InventoryOrder $order = null): float
    {
        return round(
            $this->excelDailyUsageSuom($stock, $config) * $this->safetyStockDays($stock, $config, $order),
            4
        );
    }

    public function bufferStockSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config, ?InventoryOrder $order = null): float
    {
        return round(
            $this->excelDailyUsageSuom($stock, $config) * $this->bufferStockDays($stock, $config, $order),
            4
        );
    }

    /**
     * Excel column AM: N − (safety days + buffer days).
     */
    public function daysLeftToOrder(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null, ?InventoryOrder $order = null): ?float
    {
        $stockDays = $this->stockDaysReport($stock, $config);

        if ($stockDays === null) {
            return null;
        }

        return round(
            $stockDays - $this->safetyStockDays($stock, $config, $order) - $this->bufferStockDays($stock, $config, $order),
            1
        );
    }

    /**
     * Excel column AY: when to start the ordering process.
     */
    public function orderingNotificationDate(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null, ?InventoryOrder $order = null): ?Carbon
    {
        $daysLeft = $this->daysLeftToOrder($stock, $config, $order);

        if ($daysLeft === null) {
            return null;
        }

        if ($daysLeft <= 0) {
            return Carbon::today();
        }

        $notifyLead = $this->notificationToOrderDays($stock, $config, $order);
        $daysUntilNotify = max(0, $daysLeft - $notifyLead);

        return Carbon::today()->addDays((int) round($daysUntilNotify));
    }

    /**
     * Excel F/J: purchase price per SUOM from the latest approved GRN line.
     */
    public function purchasePricePerSuom(InventoryStockLevel $stock, ?Item $item = null): float
    {
        $line = GoodsReceivedNoteLine::query()
            ->join('goods_received_notes as grn', 'grn.id', '=', 'goods_received_note_lines.goods_received_note_id')
            ->where('grn.business_id', $stock->business_id)
            ->where('grn.store_id', $stock->store_id)
            ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED)
            ->where('goods_received_note_lines.item_id', $stock->item_id)
            ->orderByDesc('grn.date_of_delivery')
            ->select('goods_received_note_lines.*')
            ->first();

        if ($line && (float) $line->sale_units_per_purchase_unit > 0) {
            return round((float) $line->purchase_price / (float) $line->sale_units_per_purchase_unit, 4);
        }

        return (float) (
            $stock->weighted_avg_cost
            ?? $stock->last_purchase_price
            ?? $item?->purchasePricePerSuom()
            ?? 0
        );
    }

    /**
     * Excel column O: M × (F/J).
     */
    public function inventoryValuationUgx(InventoryStockLevel $stock, ?Item $item = null): float
    {
        return round($this->currentStockLevelSuom($stock) * $this->purchasePricePerSuom($stock, $item), 2);
    }

    /**
     * Excel column AV: 100 × (AR − M) / AR.
     */
    public function shrinkagePercentExcel(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): ?float
    {
        $ar = $this->systemStockArSuom($stock, $config);
        $current = $this->currentStockLevelSuom($stock);

        if ($ar <= 0) {
            return null;
        }

        return round((($ar - $current) / $ar) * 100, 4);
    }

    /**
     * Excel column AW: (AR − M) × (F/J).
     */
    public function shrinkageAmountUgx(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null, ?Item $item = null): ?float
    {
        $ar = $this->systemStockArSuom($stock, $config);
        $current = $this->currentStockLevelSuom($stock);
        $delta = $ar - $current;

        if ($delta <= 0) {
            return $delta < 0 ? null : 0.0;
        }

        return round($delta * $this->purchasePricePerSuom($stock, $item), 2);
    }

    /**
     * Graduated MA window days from a threshold value (period of order for AF).
     * V if < 15; W if < 30; X if < 90; Y if < 180; Z if < 360; else Z.
     */
    public function graduatedMaWindowDays(?float $thresholdValue): int
    {
        $value = $thresholdValue ?? 0.0;

        foreach ([15, 30, 90, 180, 360] as $days) {
            if ($value < $days) {
                return $days;
            }
        }

        return 360;
    }

    /**
     * Graduated MA rate for the given threshold (period days for order-by-period).
     */
    public function graduatedMovingAverage(InventoryStockLevel $stock, ?float $thresholdValue): float
    {
        return $this->movingAverageForStock($stock, $this->graduatedMaWindowDays($thresholdValue));
    }

    /**
     * @deprecated Prefer graduatedMovingAverage() with period days for AF.
     * Kept for callers that still pass stock days.
     */
    public function graduatedMovingAverageByStockDays(InventoryStockLevel $stock, ?float $stockDaysN): float
    {
        return $this->graduatedMovingAverage($stock, $stockDaysN);
    }

    /**
     * Excel columns AI / AJ / AK: Ordering By Budget.
     * BA7 is the budget amount (UGX) from the Inventory sheet (“Order by Budget (UGX)”).
     *
     * - AI = days_left − AVERAGE(days_left)
     * - AJ = (15 × BA7 ÷ SUM(AH)) − AI
     * - AK = AJ × daily usage
     */
    public function suggestedOrderQtyBudgetDays(
        float $budgetUgxOrBa7,
        float $daysLeft,
        float $averageDaysLeft,
        float $sumTestAmount,
        float $dailyUsageSuom
    ): float {
        if ($sumTestAmount <= 0 || $dailyUsageSuom <= 0 || $budgetUgxOrBa7 <= 0) {
            return 0.0;
        }

        $gap = $daysLeft - $averageDaysLeft;
        $orderDays = max(0, (15 * $budgetUgxOrBa7 / $sumTestAmount) - $gap);

        return max(0, round($orderDays * $dailyUsageSuom, 4));
    }

    public function orderDaysBudgetAllocation(
        float $budgetUgxOrBa7,
        float $daysLeft,
        float $averageDaysLeft,
        float $sumTestAmount
    ): float {
        if ($sumTestAmount <= 0 || $budgetUgxOrBa7 <= 0) {
            return 0.0;
        }

        $gap = $daysLeft - $averageDaysLeft;

        return max(0, round((15 * $budgetUgxOrBa7 / $sumTestAmount) - $gap, 4));
    }

    /**
     * Excel column AF (period ordering):
     * max(0, (period + safety + buffer − N) × graduated_MA(period)).
     *
     * MA window is selected from the period of order (BA6):
     * V if period < 15; W if < 30; X if < 90; Y if < 180; Z if < 360; else Z.
     */
    public function suggestedOrderQtyPeriod(
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        float $periodDays,
        ?InventoryOrder $order = null
    ): float {
        $stockDays = $this->stockDaysReport($stock, $config) ?? 0;
        $coverage = $periodDays
            + $this->safetyStockDays($stock, $config, $order)
            + $this->bufferStockDays($stock, $config, $order)
            - $stockDays;

        if ($coverage <= 0) {
            return 0.0;
        }

        $rate = $this->graduatedMovingAverage($stock, $periodDays);

        if ($rate <= 0) {
            $rate = $this->excelDailyUsageSuom($stock, $config);
        }

        return max(0, round($coverage * $rate, 4));
    }

    /**
     * Daily usage rate used for period ordering (graduated MA from period days).
     */
    public function periodOrderDailyRate(
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        float $periodDays
    ): float {
        $rate = $this->graduatedMovingAverage($stock, $periodDays);

        if ($rate > 0) {
            return $rate;
        }

        return $this->excelDailyUsageSuom($stock, $config);
    }

    /**
     * Excel column AG: order qty × (F/J).
     */
    public function demandForecastAmountUgx(
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        float $orderQtySuom,
        ?Item $item = null
    ): float {
        return round($orderQtySuom * $this->purchasePricePerSuom($stock, $item), 2);
    }

    /**
     * Excel column AH: 15 × (V or AA) × (F/J).
     */
    public function budgetTestAmountUgx(InventoryStockLevel $stock, ?InventoryModuleConfig $config, ?Item $item = null): float
    {
        return round(
            15 * $this->excelDailyUsageSuom($stock, $config) * $this->purchasePricePerSuom($stock, $item),
            2
        );
    }

    private function openingQuantityAtFinancialYear(InventoryStockLevel $stock, Carbon $fyStart): float
    {
        if ($stock->opening_quantity_suom !== null) {
            return (float) $stock->opening_quantity_suom;
        }

        $lastBefore = InventoryStockMovement::query()
            ->where('business_id', $stock->business_id)
            ->where('store_id', $stock->store_id)
            ->where('item_id', $stock->item_id)
            ->where('occurred_at', '<', $fyStart)
            ->orderByDesc('occurred_at')
            ->first();

        return (float) ($lastBefore?->balance_after ?? 0);
    }

    public function safetyStockDays(InventoryStockLevel $stock, ?InventoryModuleConfig $config, ?InventoryOrder $order = null): float
    {
        if ($stock->safety_stock_days !== null) {
            return (float) $stock->safety_stock_days;
        }

        if ($order !== null && $order->safety_stock_days !== null) {
            return (float) $order->safety_stock_days;
        }

        return (float) ($config?->safety_stock_days ?? 0);
    }

    public function bufferStockDays(InventoryStockLevel $stock, ?InventoryModuleConfig $config, ?InventoryOrder $order = null): float
    {
        if ($stock->buffer_stock_days !== null) {
            return (float) $stock->buffer_stock_days;
        }

        if ($order !== null && $order->buffer_stock_days !== null) {
            return (float) $order->buffer_stock_days;
        }

        return (float) ($config?->buffer_stock_days ?? 0);
    }

    public function notificationToOrderDays(
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        ?InventoryOrder $order = null
    ): float {
        if ($order !== null && $order->notification_to_order_days !== null) {
            return (float) $order->notification_to_order_days;
        }

        return (float) ($config?->notification_to_order_days ?? 0);
    }

    public function systemQuantitySuom(InventoryStockLevel $stock): float
    {
        return (float) $stock->quantity_suom;
    }

    public function physicalQuantitySuom(InventoryStockLevel $stock): float
    {
        return $stock->physicalStockSuom();
    }

    public function usableQuantitySuom(InventoryStockLevel $stock): float
    {
        return $stock->physicalUsableQuantitySuom();
    }

    public function verifiableShrinkageSuom(InventoryStockLevel $stock): float
    {
        return round(
            (float) ($stock->damaged_quantity_suom ?? 0) + (float) ($stock->expired_quantity_suom ?? 0),
            4
        );
    }

    public function totalShrinkageSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): float
    {
        $ar = $this->systemStockArSuom($stock, $config);

        return max(0, round($ar - $this->currentStockLevelSuom($stock), 4));
    }

    public function unverifiedShrinkageSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config = null): float
    {
        return max(0, round(
            $this->totalShrinkageSuom($stock, $config) - $this->verifiableShrinkageSuom($stock),
            4
        ));
    }

    public function shrinkagePercent(InventoryStockLevel $stock): ?float
    {
        $system = $this->systemQuantitySuom($stock);
        $physical = $this->physicalQuantitySuom($stock);

        if ($physical === null || $system <= 0) {
            return null;
        }

        return round((($system - $physical) / $system) * 100, 2);
    }

    public function shrinkageAmountSuom(InventoryStockLevel $stock): ?float
    {
        $physical = $this->physicalQuantitySuom($stock);

        if ($physical === null) {
            return null;
        }

        return round($this->systemQuantitySuom($stock) - $physical, 4);
    }

    public function movingAverageForStock(InventoryStockLevel $stock, int $days): float
    {
        $column = self::MOVING_AVERAGE_WINDOWS[$days] ?? null;

        if ($column && $stock->{$column} !== null) {
            return (float) $stock->{$column};
        }

        return $this->movingAverage(
            (int) $stock->business_id,
            (int) $stock->store_id,
            (int) $stock->item_id,
            $days
        );
    }

    /** @var array<string, array<string, mixed>>|null */
    private ?array $pageMetricsCache = null;

    public function resetPageMetricsCache(): void
    {
        $this->pageMetricsCache = null;
    }

    /**
     * Pre-compute display metrics for a page of stock levels (one batch of DB queries).
     *
     * @param  iterable<int, InventoryStockLevel>  $stocks
     */
    public function warmPageMetrics(iterable $stocks, ?InventoryModuleConfig $config, float $periodDays = 30): void
    {
        $stocks = collect($stocks)->filter();

        if ($stocks->isEmpty()) {
            $this->pageMetricsCache = [];

            return;
        }

        $businessId = (int) $stocks->first()->business_id;
        $fyStart = $this->financialYearStart($businessId);

        $pairs = $stocks
            ->map(fn (InventoryStockLevel $stock): array => [
                'store_id' => (int) $stock->store_id,
                'item_id' => (int) $stock->item_id,
            ])
            ->unique(fn (array $pair): string => "{$pair['store_id']}-{$pair['item_id']}")
            ->values();

        $fyMovementSums = $this->batchMovementSums($businessId, $pairs, $fyStart);
        $openings = $this->batchOpeningQuantities($stocks, $fyStart);
        $purchasePrices = $this->batchPurchasePrices($businessId, $stocks);
        $movementSinceCount = $this->batchPhysicalMovementSums($businessId, $stocks);

        $this->pageMetricsCache = [];

        foreach ($stocks as $stock) {
            $key = $this->pageMetricKey($stock);

            $opening = $openings[$key] ?? 0.0;
            $ar = max(0, round($opening + ($fyMovementSums[$key] ?? 0.0), 4));
            $currentM = $this->currentStockLevelSuom($stock, $movementSinceCount[$key] ?? null);

            $purchasePrice = $purchasePrices[$key] ?? 0.0;
            $excelUsage = $this->excelDailyUsageFromStock($stock, $config);
            $stockDays = $excelUsage > 0 ? round($currentM / $excelUsage, 1) : null;
            $safetyDays = $this->safetyStockDays($stock, $config);
            $bufferDays = $this->bufferStockDays($stock, $config);
            $daysLeft = $stockDays !== null
                ? round($stockDays - $safetyDays - $bufferDays, 1)
                : null;

            $notifyDate = null;

            if ($daysLeft !== null) {
                if ($daysLeft <= 0) {
                    $notifyDate = Carbon::today()->format('M d, Y');
                } else {
                    $notifyLead = $this->notificationToOrderDays($stock, $config);
                    $notifyDate = Carbon::today()
                        ->addDays((int) round(max(0, $daysLeft - $notifyLead)))
                        ->format('M d, Y');
                }
            }

            $shrinkageDelta = $ar - $currentM;
            $shrinkagePct = $ar > 0 ? round(($shrinkageDelta / $ar) * 100, 4) : null;
            $shrinkageUgx = $shrinkageDelta > 0
                ? round($shrinkageDelta * $purchasePrice, 2)
                : ($shrinkageDelta < 0 ? null : 0.0);

            $orderQty = 0.0;

            if ($stockDays !== null) {
                $coverage = $periodDays + $safetyDays + $bufferDays - $stockDays;

                if ($coverage > 0) {
                    $rate = $this->graduatedMovingAverage($stock, $periodDays);

                    if ($rate <= 0) {
                        $rate = $excelUsage;
                    }

                    $orderQty = max(0, round($coverage * $rate, 4));
                }
            }

            $this->pageMetricsCache[$key] = [
                'system_ar' => $ar,
                'current_m' => $currentM,
                'current_stock' => $currentM,
                'shrinkage_qty' => max(0, $shrinkageDelta),
                'shrinkage_pct' => $shrinkagePct,
                'shrinkage_ugx' => $shrinkageUgx,
                'stock_days' => $stockDays,
                'days_left' => $daysLeft,
                'notify_date' => $notifyDate,
                'safety_days' => $safetyDays,
                'buffer_days' => $bufferDays,
                'safety_stock_suom' => round($excelUsage * $safetyDays, 4),
                'buffer_stock_suom' => round($excelUsage * $bufferDays, 4),
                'purchase_price' => $purchasePrice,
                'valuation' => round($currentM * $purchasePrice, 2),
                'excel_daily_usage' => $excelUsage,
                'suggested_order_qty' => $orderQty,
                'demand_forecast_amount' => round($orderQty * $purchasePrice, 2),
                'budget_test_amount' => round(15 * $excelUsage * $purchasePrice, 2),
            ];
        }
    }

    public function pageMetric(
        InventoryStockLevel $stock,
        string $field,
        ?InventoryModuleConfig $config = null,
        ?Item $item = null,
        float $periodDays = 30,
    ): mixed {
        $key = $this->pageMetricKey($stock);

        if ($this->pageMetricsCache === null || ! array_key_exists($key, $this->pageMetricsCache)) {
            $this->warmPageMetrics([$stock], $config, $periodDays);
        }

        return $this->pageMetricsCache[$key][$field] ?? null;
    }

    private function pageMetricKey(InventoryStockLevel $stock): string
    {
        return "{$stock->store_id}-{$stock->item_id}";
    }

    private function excelDailyUsageFromStock(InventoryStockLevel $stock, ?InventoryModuleConfig $config): float
    {
        $ma15 = $this->movingAverageForStock($stock, 15);

        if ($ma15 > 0) {
            return $ma15;
        }

        return (float) ($config?->fixed_daily_average_suom ?? 0);
    }

    /**
     * @param  Collection<int, array{store_id: int, item_id: int}>  $pairs
     * @return array<string, float>
     */
    private function batchMovementSums(int $businessId, Collection $pairs, Carbon $since): array
    {
        if ($pairs->isEmpty()) {
            return [];
        }

        $rows = StoreItemPairQuery::whereInPairs(
            InventoryStockMovement::query()
                ->where('business_id', $businessId)
                ->where('occurred_at', '>=', $since),
            $pairs,
            'store_id',
            'item_id'
        )
            ->selectRaw('store_id, item_id, SUM(quantity_delta) as total')
            ->groupBy('store_id', 'item_id')
            ->get();

        $sums = [];

        foreach ($rows as $row) {
            $sums["{$row->store_id}-{$row->item_id}"] = (float) $row->total;
        }

        return $sums;
    }

    /**
     * @param  Collection<int, InventoryStockLevel>  $stocks
     * @return array<string, float>
     */
    private function batchOpeningQuantities(Collection $stocks, Carbon $fyStart): array
    {
        $openings = [];
        $needLookup = [];

        foreach ($stocks as $stock) {
            $key = $this->pageMetricKey($stock);

            if ($stock->opening_quantity_suom !== null) {
                $openings[$key] = (float) $stock->opening_quantity_suom;
            } else {
                $needLookup[] = $stock;
            }
        }

        if ($needLookup === []) {
            return $openings;
        }

        $businessId = (int) $needLookup[0]->business_id;

        $rows = InventoryStockMovement::query()
            ->from('inventory_stock_movements as m')
            ->joinSub(
                InventoryStockMovement::query()
                    ->where('business_id', $businessId)
                    ->where('occurred_at', '<', $fyStart)
                    ->where(function ($query) use ($needLookup): void {
                        foreach ($needLookup as $stock) {
                            $query->orWhere(function ($inner) use ($stock): void {
                                $inner->where('store_id', $stock->store_id)
                                    ->where('item_id', $stock->item_id);
                            });
                        }
                    })
                    ->selectRaw('store_id, item_id, MAX(occurred_at) as max_occurred_at')
                    ->groupBy('store_id', 'item_id'),
                'latest',
                function ($join): void {
                    $join->on('m.store_id', '=', 'latest.store_id')
                        ->on('m.item_id', '=', 'latest.item_id')
                        ->on('m.occurred_at', '=', 'latest.max_occurred_at');
                }
            )
            ->where('m.business_id', $businessId)
            ->select(['m.store_id', 'm.item_id', 'm.balance_after'])
            ->get();

        foreach ($rows as $row) {
            $openings["{$row->store_id}-{$row->item_id}"] = (float) $row->balance_after;
        }

        foreach ($needLookup as $stock) {
            $key = $this->pageMetricKey($stock);
            $openings[$key] = $openings[$key] ?? 0.0;
        }

        return $openings;
    }

    /**
     * @param  Collection<int, InventoryStockLevel>  $stocks
     * @return array<string, float>
     */
    private function batchPhysicalMovementSums(int $businessId, Collection $stocks): array
    {
        $sinceByKey = [];

        foreach ($stocks as $stock) {
            if ($stock->physical_counted_at !== null) {
                $sinceByKey[$this->pageMetricKey($stock)] = Carbon::parse($stock->physical_counted_at)->startOfDay();
            }
        }

        if ($sinceByKey === []) {
            return [];
        }

        $minSince = collect($sinceByKey)->min();

        $rows = InventoryStockMovement::query()
            ->where('business_id', $businessId)
            ->where('occurred_at', '>=', $minSince)
            ->where(function ($query) use ($sinceByKey): void {
                foreach ($sinceByKey as $key => $since) {
                    [$storeId, $itemId] = array_map('intval', explode('-', $key));
                    $query->orWhere(function ($inner) use ($storeId, $itemId): void {
                        $inner->where('store_id', $storeId)->where('item_id', $itemId);
                    });
                }
            })
            ->select(['store_id', 'item_id', 'occurred_at', 'quantity_delta'])
            ->get();

        $sums = [];

        foreach ($rows as $row) {
            $key = "{$row->store_id}-{$row->item_id}";
            $since = $sinceByKey[$key] ?? null;

            if ($since === null || Carbon::parse($row->occurred_at)->lt($since)) {
                continue;
            }

            $sums[$key] = ($sums[$key] ?? 0.0) + (float) $row->quantity_delta;
        }

        return $sums;
    }

    /**
     * @param  Collection<int, InventoryStockLevel>  $stocks
     * @return array<string, float>
     */
    private function batchPurchasePrices(int $businessId, Collection $stocks): array
    {
        $prices = [];
        $needGrn = [];

        foreach ($stocks as $stock) {
            $key = $this->pageMetricKey($stock);
            $fromStock = $stock->weighted_avg_cost ?? $stock->last_purchase_price;

            if ($fromStock !== null && (float) $fromStock > 0) {
                $prices[$key] = (float) $fromStock;
            } else {
                $needGrn[] = $stock;
            }
        }

        if ($needGrn === []) {
            return $prices;
        }

        $rows = GoodsReceivedNoteLine::query()
            ->from('goods_received_note_lines as lines')
            ->join('goods_received_notes as grn', 'grn.id', '=', 'lines.goods_received_note_id')
            ->where('grn.business_id', $businessId)
            ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED)
            ->where(function ($query) use ($needGrn): void {
                foreach ($needGrn as $stock) {
                    $query->orWhere(function ($inner) use ($stock): void {
                        $inner->where('grn.store_id', $stock->store_id)
                            ->where('lines.item_id', $stock->item_id);
                    });
                }
            })
            ->selectRaw('grn.store_id, lines.item_id, MAX(grn.date_of_delivery) as last_delivery')
            ->groupBy('grn.store_id', 'lines.item_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->store_id}-{$row->item_id}");

        $lineRows = GoodsReceivedNoteLine::query()
            ->from('goods_received_note_lines as lines')
            ->join('goods_received_notes as grn', 'grn.id', '=', 'lines.goods_received_note_id')
            ->where('grn.business_id', $businessId)
            ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED)
            ->where(function ($query) use ($rows): void {
                foreach ($rows as $key => $row) {
                    $query->orWhere(function ($inner) use ($row): void {
                        $inner->where('grn.store_id', $row->store_id)
                            ->where('lines.item_id', $row->item_id)
                            ->whereDate('grn.date_of_delivery', $row->last_delivery);
                    });
                }
            })
            ->select(['grn.store_id', 'lines.item_id', 'lines.purchase_price', 'lines.sale_units_per_purchase_unit'])
            ->get();

        foreach ($lineRows as $line) {
            $key = "{$line->store_id}-{$line->item_id}";

            if (isset($prices[$key])) {
                continue;
            }

            if ((float) $line->sale_units_per_purchase_unit > 0) {
                $prices[$key] = round((float) $line->purchase_price / (float) $line->sale_units_per_purchase_unit, 4);
            }
        }

        foreach ($needGrn as $stock) {
            $key = $this->pageMetricKey($stock);
            $prices[$key] = $prices[$key]
                ?? (float) ($stock->item?->purchasePricePerSuom() ?? 0);
        }

        return $prices;
    }

    public function recordConsumption(
        int $businessId,
        int $storeId,
        int $itemId,
        string $date,
        float $quantitySuom,
        string $source = InventoryDailyConsumption::SOURCE_MANUAL,
        ?int $recordedByUserId = null,
        ?string $notes = null,
        ?DateTimeInterface $occurredAt = null,
        ?int $saleId = null
    ): InventoryDailyConsumption {
        if ($quantitySuom <= 0) {
            throw new \InvalidArgumentException('Consumption quantity must be greater than zero.');
        }

        $occurredAt = Carbon::parse($occurredAt ?? $date)->copy();

        return DB::transaction(function () use ($businessId, $storeId, $itemId, $date, $quantitySuom, $source, $recordedByUserId, $notes, $occurredAt, $saleId) {
            $consumption = InventoryDailyConsumption::query()->firstOrNew([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
                'consumption_date' => $date,
                'source' => $source,
            ]);

            $consumption->quantity_suom = (float) ($consumption->quantity_suom ?? 0) + $quantitySuom;

            if ($notes !== null) {
                $consumption->notes = $notes;
            }

            if ($recordedByUserId !== null) {
                $consumption->recorded_by_user_id = $recordedByUserId;
            }

            $consumption->save();

            app(InventoryMonthlyConsumptionService::class)->syncMonthFromDaily(
                $businessId,
                $storeId,
                $itemId,
                $date
            );

            InventoryConsumptionEvent::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
                'quantity_suom' => $quantitySuom,
                'occurred_at' => $occurredAt,
                'source' => $source,
                'sale_id' => $saleId,
            ]);

            $stock = InventoryStockLevel::firstOrCreate(
                [
                    'business_id' => $businessId,
                    'store_id' => $storeId,
                    'item_id' => $itemId,
                ],
                ['quantity_suom' => 0, 'physical_quantity_suom' => 0]
            );

            $this->recalculateForStockLevel($stock);

            $balanceBefore = (float) $stock->quantity_suom;
            $balanceAfter = $stock->applyOnHandBalance(max(0, $balanceBefore - $quantitySuom));

            $stock->save();

            InventoryStockMovement::create([
                'business_id' => $businessId,
                'item_id' => $itemId,
                'store_id' => $storeId,
                'movement_type' => InventoryStockMovement::TYPE_CONSUMPTION,
                'quantity_delta' => -$quantitySuom,
                'balance_after' => $balanceAfter,
                'reference_label' => 'Consumption '.$occurredAt->format('Y-m-d H:i'),
                'recorded_by_user_id' => $recordedByUserId,
                'occurred_at' => $occurredAt,
            ]);

            return $consumption;
        });
    }

    /**
     * @param  array<int, array{item_id: int, quantity_suom: float}>  $lines
     */
    public function recordManyConsumptions(
        int $businessId,
        int $storeId,
        string $date,
        array $lines,
        ?int $recordedByUserId = null,
        ?string $notes = null
    ): int {
        return DB::transaction(function () use ($businessId, $storeId, $date, $lines, $recordedByUserId, $notes) {
            $count = 0;

            foreach ($lines as $line) {
                $qty = (float) ($line['quantity_suom'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $this->recordConsumption(
                    $businessId,
                    $storeId,
                    (int) $line['item_id'],
                    $date,
                    $qty,
                    InventoryDailyConsumption::SOURCE_MANUAL,
                    $recordedByUserId,
                    $notes
                );

                $count++;
            }

            return $count;
        });
    }
}
