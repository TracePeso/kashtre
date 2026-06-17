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
use Carbon\Carbon;
use DateTimeInterface;
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

    public function financialYearStart(?InventoryModuleConfig $config, ?Carbon $asOf = null): Carbon
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $month = max(1, min(12, (int) ($config?->financial_year_start_month ?? 1)));
        $start = Carbon::create($asOf->year, $month, 1)->startOfDay();

        if ($asOf->lt($start)) {
            $start->subYear();
        }

        return $start;
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
        $fyStart = $this->financialYearStart($config);
        $opening = $this->openingQuantityAtFinancialYear($stock, $fyStart);

        return max(0, round($opening + $this->movementSumSince($stock, $fyStart), 4));
    }

    /**
     * Excel column M: physical count anchor + movements since last count.
     */
    public function currentStockLevelSuom(InventoryStockLevel $stock): float
    {
        if ($stock->physical_counted_at === null) {
            return (float) $stock->quantity_suom;
        }

        $since = Carbon::parse($stock->physical_counted_at)->startOfDay();
        $anchor = (float) ($stock->physical_quantity_suom ?? 0);

        return max(0, round($anchor + $this->movementSumSince($stock, $since), 4));
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
            ?? $item?->default_price
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
     * Graduated MA for order qty (Excel AF): pick window based on stock days N.
     */
    public function graduatedMovingAverageByStockDays(InventoryStockLevel $stock, ?float $stockDaysN): float
    {
        if ($stockDaysN === null || $stockDaysN <= 0) {
            return $this->movingAverageForStock($stock, 360);
        }

        foreach ([15, 30, 90, 180, 360] as $days) {
            if ($stockDaysN < $days) {
                return $this->movingAverageForStock($stock, $days);
            }
        }

        return $this->movingAverageForStock($stock, 360);
    }

    /**
     * Excel column AF (period ordering): max(0, (period + safety + buffer − N) × graduated MA).
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

        $rate = $this->graduatedMovingAverageByStockDays($stock, $stockDays);

        if ($rate <= 0) {
            $rate = $this->excelDailyUsageSuom($stock, $config);
        }

        return max(0, round($coverage * $rate, 4));
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

    public function physicalQuantitySuom(InventoryStockLevel $stock): ?float
    {
        if ($stock->physical_quantity_suom === null) {
            return null;
        }

        return (float) $stock->physical_quantity_suom;
    }

    public function usableQuantitySuom(InventoryStockLevel $stock): float
    {
        $physical = $this->physicalQuantitySuom($stock);
        $base = $physical ?? $this->systemQuantitySuom($stock);
        $verifiable = (float) ($stock->damaged_quantity_suom ?? 0) + (float) ($stock->expired_quantity_suom ?? 0);

        return max(0, round($base - $verifiable, 4));
    }

    public function verifiableShrinkageSuom(InventoryStockLevel $stock): float
    {
        return round(
            (float) ($stock->damaged_quantity_suom ?? 0) + (float) ($stock->expired_quantity_suom ?? 0),
            4
        );
    }

    public function totalShrinkageSuom(InventoryStockLevel $stock): ?float
    {
        $physical = $this->physicalQuantitySuom($stock);

        if ($physical === null) {
            return null;
        }

        return max(0, round($this->systemQuantitySuom($stock) - $physical, 4));
    }

    public function unverifiedShrinkageSuom(InventoryStockLevel $stock): ?float
    {
        return $this->totalShrinkageSuom($stock);
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
                ['quantity_suom' => 0]
            );

            $this->recalculateForStockLevel($stock);

            $balanceBefore = (float) $stock->quantity_suom;
            $balanceAfter = max(0, $balanceBefore - $quantitySuom);

            $stock->update(['quantity_suom' => $balanceAfter]);

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
