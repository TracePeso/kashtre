<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use Carbon\Carbon;
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

    public function safetyStockDays(InventoryStockLevel $stock, ?InventoryModuleConfig $config): float
    {
        if ($stock->safety_stock_days !== null) {
            return (float) $stock->safety_stock_days;
        }

        return (float) ($config?->safety_stock_days ?? 0);
    }

    public function bufferStockDays(InventoryStockLevel $stock, ?InventoryModuleConfig $config): float
    {
        if ($stock->buffer_stock_days !== null) {
            return (float) $stock->buffer_stock_days;
        }

        return (float) ($config?->buffer_stock_days ?? 0);
    }

    public function safetyStockSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config): float
    {
        return round(
            $this->effectiveDailyUsage($stock, $config) * $this->safetyStockDays($stock, $config),
            4
        );
    }

    public function bufferStockSuom(InventoryStockLevel $stock, ?InventoryModuleConfig $config): float
    {
        return round(
            $this->effectiveDailyUsage($stock, $config) * $this->bufferStockDays($stock, $config),
            4
        );
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
        ?string $notes = null
    ): InventoryDailyConsumption {
        return DB::transaction(function () use ($businessId, $storeId, $itemId, $date, $quantitySuom, $source, $recordedByUserId, $notes) {
            $consumption = InventoryDailyConsumption::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'store_id' => $storeId,
                    'item_id' => $itemId,
                    'consumption_date' => $date,
                    'source' => $source,
                ],
                [
                    'quantity_suom' => $quantitySuom,
                    'notes' => $notes,
                    'recorded_by_user_id' => $recordedByUserId,
                ]
            );

            $stock = InventoryStockLevel::firstOrCreate(
                [
                    'business_id' => $businessId,
                    'store_id' => $storeId,
                    'item_id' => $itemId,
                ],
                ['quantity_suom' => 0]
            );

            $this->recalculateForStockLevel($stock);

            if ($quantitySuom > 0) {
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
                    'reference_label' => 'Daily consumption '.$date,
                    'recorded_by_user_id' => $recordedByUserId,
                    'occurred_at' => Carbon::parse($date)->endOfDay(),
                ]);
            }

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
