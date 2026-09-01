<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\ItemImportanceCategory;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOrderService
{
    /** Consumption rate window — auto-applied (15-day MA per Excel V/AA). */
    public const AUTO_CONSUMPTION_RATE_DAYS = 15;

    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics,
        private readonly InventoryDaysOfStockService $daysOfStock,
    ) {}

    public function generateOrderNumber(int $businessId, string $orderType = InventoryOrder::TYPE_EXTERNAL): string
    {
        $docPrefix = $orderType === InventoryOrder::TYPE_INTERNAL ? 'INT' : 'RFQ';
        $prefix = $docPrefix.'-'.now()->format('Ymd');
        $count = InventoryOrder::query()
            ->where('business_id', $businessId)
            ->where('order_number', 'like', $prefix.'%')
            ->count();

        return $prefix.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(
        int $businessId,
        int $storeId,
        User $user,
        ?string $importanceFilter = null,
        ?string $budgetMode = null,
        ?float $budgetValue = null,
        ?float $periodOfOrderDays = null,
        ?string $notes = null,
        ?int $groupId = null,
        ?int $subgroupId = null,
        ?float $peakPeriodPercent = null,
        ?float $peakConsumptionIncreasePercent = null,
        ?float $safetyStockDays = null,
        ?float $bufferStockDays = null,
        ?float $notificationToOrderDays = null,
        ?array $itemIds = null,
        ?int $supplierId = null,
        string $orderType = InventoryOrder::TYPE_EXTERNAL,
        ?int $sourceStoreId = null,
        string $forecastBasis = InventoryOrder::FORECAST_CONSUMPTION,
    ): InventoryOrder {
        $normalizedItemIds = $this->normalizeItemIds($itemIds);
        $forecastBasis = in_array($forecastBasis, [
            InventoryOrder::FORECAST_CONSUMPTION,
            InventoryOrder::FORECAST_DEMAND,
        ], true) ? $forecastBasis : InventoryOrder::FORECAST_CONSUMPTION;

        return DB::transaction(function () use ($businessId, $storeId, $user, $importanceFilter, $budgetMode, $budgetValue, $periodOfOrderDays, $notes, $groupId, $subgroupId, $peakPeriodPercent, $peakConsumptionIncreasePercent, $safetyStockDays, $bufferStockDays, $notificationToOrderDays, $normalizedItemIds, $supplierId, $orderType, $sourceStoreId, $forecastBasis) {
            $config = InventoryModuleConfig::query()
                ->forBusiness($businessId)
                ->active()
                ->first();

            if ($orderType === InventoryOrder::TYPE_EXTERNAL && $supplierId) {
                Supplier::query()
                    ->where('business_id', $businessId)
                    ->whereKey($supplierId)
                    ->firstOrFail();
            }

            $order = InventoryOrder::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'order_type' => $orderType,
                'source_store_id' => $orderType === InventoryOrder::TYPE_INTERNAL ? $sourceStoreId : null,
                'supplier_id' => $orderType === InventoryOrder::TYPE_INTERNAL ? null : $supplierId,
                'order_number' => $this->generateOrderNumber($businessId, $orderType),
                'status' => InventoryOrder::STATUS_DRAFT,
                'importance_filter' => $importanceFilter,
                'group_id' => $groupId,
                'subgroup_id' => $subgroupId,
                'item_ids' => $normalizedItemIds,
                'budget_mode' => $budgetMode,
                'forecast_basis' => $forecastBasis,
                'budget_value' => $budgetValue,
                'moving_average_days' => in_array($budgetMode, [
                    InventoryOrder::BUDGET_MODE_DAYS,
                    InventoryOrder::BUDGET_MODE_AMOUNT,
                ], true)
                    ? self::AUTO_CONSUMPTION_RATE_DAYS
                    : $this->analytics->graduatedMaWindowDays(
                        $this->storedPeriodOfOrderDays($budgetMode, $periodOfOrderDays, $config)
                    ),
                'period_of_order_days' => $this->storedPeriodOfOrderDays($budgetMode, $periodOfOrderDays, $config),
                'safety_stock_days' => $safetyStockDays ?? (float) ($config?->safety_stock_days ?? 0),
                'buffer_stock_days' => $bufferStockDays ?? (float) ($config?->buffer_stock_days ?? 0),
                'notification_to_order_days' => $notificationToOrderDays ?? (float) ($config?->notification_to_order_days ?? 0),
                'peak_period_percent' => max(0, (float) ($peakPeriodPercent ?? 0)),
                'peak_consumption_increase_percent' => max(0, (float) ($peakConsumptionIncreasePercent ?? 0)),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            $this->populateLines($order);

            $order = $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'lines.item.suppliers', 'lines.supplier', 'store', 'sourceStore', 'supplier']);

            $this->refreshRfqDocument($order);

            return $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'lines.item.suppliers', 'lines.supplier', 'store', 'sourceStore', 'supplier']);
        });
    }

    public function refreshRfqDocument(InventoryOrder $order): void
    {
        if (! $order->isExternal() || ! $order->isDraft() || $order->lines()->count() < 1) {
            return;
        }

        app(InventoryProcurementPdfService::class)->storeRfqDocument($order->fresh([
            'lines.item.itemUnit',
            'store',
            'supplier',
            'business',
            'createdBy',
            'group',
            'subgroup',
        ]));
    }

    public function populateLines(InventoryOrder $order): void
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();

        $stockLevels = $this->stockLevelsForOrder($order);

        $order->lines()->delete();

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_DAYS && $order->budget_value > 0) {
            $this->populateBudgetDaysLines($order, $stockLevels, $config);
            $this->snapshotInitialOrderTotal($order->fresh(['lines']));

            return;
        }

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_AMOUNT && $order->budget_value > 0) {
            // Excel BA7 is UGX ("Order by Budget (UGX)"), not days.
            $ba7Ugx = (float) $order->budget_value;
            $rows = $this->budgetCandidateRows($order, $stockLevels, $config);
            $sumAh = $this->budgetSumTestAmount($rows);
            $poolDays = ($sumAh > 0 && $ba7Ugx > 0)
                ? round(15 * $ba7Ugx / $sumAh, 4)
                : null;

            $order->update(['period_of_order_days' => $poolDays]);
            $this->populateBudgetDaysLines($order, $stockLevels, $config, $ba7Ugx);
            $this->snapshotInitialOrderTotal($order->fresh(['lines']));
            // Cap hard: AH→AL can still overshoot slightly; never exceed entered UGX.
            $this->applyAmountCapConstraints($order->fresh(['lines']));

            return;
        }

        $periodDays = $this->periodDaysForCalculation($order, $config);
        $peakIncrease = max(0, (float) ($order->peak_consumption_increase_percent ?? 0));
        $maWindowDays = $order->usesDemandForecast()
            ? $this->daysOfStock->forecastWindowDays($periodDays)
            : $this->analytics->graduatedMaWindowDays($periodDays);

        if ((int) ($order->moving_average_days ?? 0) !== $maWindowDays) {
            $order->update(['moving_average_days' => $maWindowDays]);
        }

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            // V / AA — 15-day rate used for stock days N = M ÷ (V or AA); demand uses demand ledger.
            $vDaily = $this->dailyUsageForOrder($order, $stock, $config, 15);
            // Period AF rate — graduated / windowed from BA6; used only for order qty.
            $periodRate = $this->periodRateForOrder($order, $stock, $config, $periodDays);

            if ($periodRate <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $baseSuggested = $this->suggestedQtyPeriodForOrder($order, $stock, $config, $periodDays);

            if ($baseSuggested <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $arStock = $this->analytics->systemStockArSuom($stock, $config);
            $currentStock = $this->analytics->currentStockLevelSuom($stock);
            $stockDays = $this->stockDaysForOrder($order, $stock, $config);
            $daysLeft = $this->analytics->daysLeftToOrder($stock, $config, $order);
            $unitPrice = $this->analytics->purchasePricePerSuom($stock, $item);

            $this->createOrderLine(
                $order,
                $item,
                $baseSuggested,
                $vDaily,
                $arStock,
                $currentStock,
                $stockDays,
                $daysLeft,
                null,
                $unitPrice,
                $peakIncrease
            );
        }

        $this->applyBudgetConstraints($order->fresh(['lines']), $config);
        $this->snapshotInitialOrderTotal($order->fresh(['lines']));
    }

    public function snapshotInitialOrderTotal(InventoryOrder $order): void
    {
        // Amount budget: cap is the UGX the user entered (order total may be ≤ that).
        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_AMOUNT && (float) ($order->budget_value ?? 0) > 0) {
            $order->update(['initial_order_total' => (float) $order->budget_value]);

            return;
        }

        $total = $order->orderTotal();

        if ($total > 0) {
            $order->update(['initial_order_total' => $total]);
        }
    }

    /**
     * Excel budget path AH–AL with BA7 = budget value.
     * For amount mode BA7 is UGX; for legacy days mode BA7 was stored as days.
     *
     * @param  Collection<int, InventoryStockLevel>  $stockLevels
     */
    private function populateBudgetDaysLines(
        InventoryOrder $order,
        Collection $stockLevels,
        ?InventoryModuleConfig $config,
        ?float $ba7Override = null
    ): void {
        $ba7 = $ba7Override !== null
            ? max(0.01, (float) $ba7Override)
            : max(0.01, (float) $order->budget_value);
        $peakIncrease = max(0, (float) ($order->peak_consumption_increase_percent ?? 0));
        $rows = $this->budgetCandidateRows($order, $stockLevels, $config);

        if ($rows === []) {
            return;
        }

        $avgDaysLeft = $this->budgetAverageDaysLeft($rows);
        $sumTestAmount = $this->budgetSumTestAmount($rows);

        foreach ($rows as $row) {
            $orderDays = $this->analytics->orderDaysBudgetAllocation(
                $ba7,
                $row['days_left'],
                $avgDaysLeft,
                $sumTestAmount
            );
            $baseSuggested = $this->analytics->suggestedOrderQtyBudgetDays(
                $ba7,
                $row['days_left'],
                $avgDaysLeft,
                $sumTestAmount,
                $row['daily_avg']
            );

            if ($baseSuggested <= 0 && ! $this->shouldKeepSelectedItem($order, $row['item'])) {
                continue;
            }

            $this->createOrderLine(
                $order,
                $row['item'],
                $baseSuggested,
                $row['daily_avg'],
                $row['ar_stock'],
                $row['current_stock'],
                $row['stock_days'],
                $row['days_left'],
                $orderDays,
                $row['unit_price'],
                $peakIncrease
            );
        }
    }

    /**
     * Candidate rows for AH→AL.
     * Overstocked items (stock days > 366) are kept when explicitly selected so the
     * line count matches the user’s selection; they get AJ = 0. AVERAGE(AM) is
     * computed later only from items that still need stock.
     *
     * @param  Collection<int, InventoryStockLevel>  $stockLevels
     * @return list<array<string, mixed>>
     */
    private function budgetCandidateRows(
        InventoryOrder $order,
        Collection $stockLevels,
        ?InventoryModuleConfig $config
    ): array {
        $rows = [];

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            $dailyAvg = $this->dailyUsageForOrder($order, $stock, $config, 15);
            $explicitlySelected = $this->shouldKeepSelectedItem($order, $item);

            if ($dailyAvg <= 0 && ! $explicitlySelected) {
                continue;
            }

            $stockDays = $this->stockDaysForOrder($order, $stock, $config);

            // Overstocked: skip unless the user picked this item (keep selection count).
            if (! $explicitlySelected && $stockDays !== null && $stockDays > 366) {
                continue;
            }

            $daysLeft = $this->analytics->daysLeftToOrder($stock, $config, $order);

            if ($daysLeft === null && ! $explicitlySelected) {
                continue;
            }

            $testAmount = $this->analytics->budgetTestAmountUgx($stock, $config, $item);

            if ($testAmount <= 0 && ! $explicitlySelected) {
                continue;
            }

            $rows[] = [
                'stock' => $stock,
                'item' => $item,
                'days_left' => $daysLeft ?? 0,
                'daily_avg' => $dailyAvg,
                'test_amount' => $testAmount,
                'unit_price' => $this->analytics->purchasePricePerSuom($stock, $item),
                'ar_stock' => $this->analytics->systemStockArSuom($stock, $config),
                'current_stock' => $this->analytics->currentStockLevelSuom($stock),
                'stock_days' => $stockDays,
            ];
        }

        return $rows;
    }

    /**
     * AVERAGE(AM) for AI — ignore overstocked lines so they do not explode AJ for urgent items.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function budgetAverageDaysLeft(array $rows): float
    {
        $needy = collect($rows)->filter(function (array $row): bool {
            $stockDays = $row['stock_days'] ?? null;

            return $stockDays === null || (float) $stockDays <= 366;
        });

        if ($needy->isEmpty()) {
            return (float) collect($rows)->avg('days_left');
        }

        return (float) $needy->avg('days_left');
    }

    /**
     * Σ AH for AJ — same portfolio as lines, but overstocked AH is optional.
     * Excel sums all AH; we sum only items that can receive order days (stock days ≤ 366)
     * so the day pool is spent on items that need stock. Overstocked selected lines stay
     * on the order with AJ = 0.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function budgetSumTestAmount(array $rows): float
    {
        $needy = collect($rows)->filter(function (array $row): bool {
            $stockDays = $row['stock_days'] ?? null;

            return $stockDays === null || (float) $stockDays <= 366;
        });

        if ($needy->isEmpty()) {
            return (float) collect($rows)->sum('test_amount');
        }

        return (float) $needy->sum('test_amount');
    }

    /**
     * Excel-aligned calculation audit for an order (from line snapshots at generation time).
     *
     * @return array{
     *     method: string,
     *     period_days: float,
     *     safety_days: float,
     *     buffer_days: float,
     *     peak_period_percent: float,
     *     peak_increase_percent: float,
     *     peak_impact_percent: float,
     *     budget_mode: ?string,
     *     budget_value: ?float,
     *     order_total: float,
     *     scale_factor: ?float,
     *     ah_sum_test_amount: ?float,
     *     am_average_days_left: ?float,
     *     ba7_budget_days: ?float,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function calculationBreakdown(InventoryOrder $order): array
    {
        $order->loadMissing(['lines.item.itemUnit']);

        if (in_array($order->budget_mode, [
            InventoryOrder::BUDGET_MODE_DAYS,
            InventoryOrder::BUDGET_MODE_AMOUNT,
        ], true)) {
            return $this->calculationBreakdownBudgetDays($order);
        }

        return $this->calculationBreakdownPeriodOrAmount($order);
    }

    /**
     * Excel AH→AL audit: BA7 stock-days budget path (entered days, or days derived from UGX).
     *
     * @return array<string, mixed>
     */
    private function calculationBreakdownBudgetDays(InventoryOrder $order): array
    {
        $isAmount = $order->budget_mode === InventoryOrder::BUDGET_MODE_AMOUNT;
        // Excel BA7: for amount mode this is UGX; legacy days mode stored days in budget_value.
        $ba7 = (float) ($order->budget_value ?? 0);
        $budgetUgx = $isAmount ? $ba7 : null;
        $poolDays = $isAmount ? (float) ($order->period_of_order_days ?? 0) : $ba7;
        $safety = (float) ($order->safety_stock_days ?? 0);
        $buffer = (float) ($order->buffer_stock_days ?? 0);
        $peakPeriod = (float) ($order->peak_period_percent ?? 0);
        $peakIncrease = (float) ($order->peak_consumption_increase_percent ?? 0);
        $peakImpact = self::computePeakImpactPercent($peakPeriod, $peakIncrease);
        $orderTotal = $order->orderTotal();

        // Match populate: AVERAGE(AM) and Σ AH use only lines that still need stock (N ≤ 366).
        $needyLines = $order->lines->filter(function ($line) {
            $n = $line->stock_days_at_order;

            return $n === null || (float) $n <= 366;
        });

        $amForAverage = $needyLines
            ->map(fn ($line) => $line->days_left_at_order !== null ? (float) $line->days_left_at_order : null)
            ->filter(fn ($v) => $v !== null)
            ->values();

        if ($amForAverage->isEmpty()) {
            $amForAverage = $order->lines
                ->map(fn ($line) => $line->days_left_at_order !== null ? (float) $line->days_left_at_order : null)
                ->filter(fn ($v) => $v !== null)
                ->values();
        }

        $avgAm = $amForAverage->isNotEmpty() ? (float) $amForAverage->avg() : 0.0;
        $amSumForAverage = $amForAverage->isNotEmpty() ? (float) $amForAverage->sum() : 0.0;

        $sumAh = 0.0;
        $ahLinesForSum = $needyLines->isNotEmpty() ? $needyLines : $order->lines;
        foreach ($ahLinesForSum as $line) {
            $v = (float) ($line->daily_average_suom ?? 0);
            $price = (float) ($line->unit_price ?? 0);
            $sumAh += round(15 * $v * $price, 2);
        }

        $ahParts = [];
        $amParts = [];
        $lineRows = [];
        $unscaledTotal = 0.0;

        foreach ($order->lines as $line) {
            $m = (float) ($line->current_stock_suom ?? 0);
            $n = $line->stock_days_at_order !== null ? (float) $line->stock_days_at_order : null;
            $am = $line->days_left_at_order !== null ? (float) $line->days_left_at_order : null;
            $v = (float) ($line->daily_average_suom ?? 0);
            $base = (float) ($line->base_suggested_quantity_suom ?? 0);
            $suggested = (float) ($line->suggested_quantity_suom ?? 0);
            $qty = (float) ($line->order_quantity_suom ?? 0);
            $price = (float) ($line->unit_price ?? 0);
            $lineTotal = (float) ($line->line_total ?? 0);
            $linePeakImpact = (float) ($line->peak_impact_percent ?? $peakImpact);
            $ajStored = $line->order_days !== null ? (float) $line->order_days : null;
            $isNeedy = $n === null || $n <= 366;

            $ah = round(15 * $v * $price, 2);
            $ai = $am !== null ? round($am - $avgAm, 4) : null;
            $ajExpected = ($sumAh > 0 && $ba7 > 0 && $ai !== null)
                ? max(0, round((15 * $ba7 / $sumAh) - $ai, 4))
                : 0.0;
            $akExpected = $v > 0 ? max(0, round($ajExpected * $v, 4)) : 0.0;
            $expectedAfterPeak = $this->applyPeakToSuggestedQuantity($akExpected, $linePeakImpact);
            $alExpected = round($expectedAfterPeak * $price, 2);
            $unscaledTotal += $alExpected;

            $ahParts[] = [
                'item_name' => $line->item?->name ?? '—',
                'v' => $v,
                'price' => $price,
                'ah' => $ah,
                'included_in_sum' => $isNeedy,
                'stock_days' => $n,
            ];

            if ($am !== null) {
                $amParts[] = [
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->name ?? '—',
                    'n' => $n,
                    'am' => $am,
                    'included_in_average' => $isNeedy,
                    'stock_days' => $n,
                ];
            }

            $vBreakdown = $this->fifteenDayUsageBreakdown(
                (int) $order->business_id,
                (int) $order->store_id,
                (int) $line->item_id,
                Carbon::parse($order->created_at ?? now())
            );

            $skippedReason = null;
            if (! $isNeedy) {
                $skippedReason = 'Stock days > 366 — kept on order with qty 0; left out of AVERAGE(AM) and Σ AH';
            } elseif ($sumAh <= 0) {
                $skippedReason = 'SUM(AH) ≤ 0 — cannot allocate budget';
            } elseif ($v <= 0) {
                $skippedReason = 'daily usage (V/AA) is zero';
            } elseif ($akExpected <= 0 && $qty <= 0) {
                $skippedReason = 'AJ ≤ 0 after urgency gap (AI) — no order days for this line';
            }

            $lineRows[] = [
                'item_id' => $line->item_id,
                'item_name' => $line->item?->name ?? '—',
                'item_code' => $line->item?->code,
                'suom' => $line->item?->itemUnit?->name,
                'm_current_stock' => $m,
                'n_stock_days' => $n,
                'am_days_left' => $am,
                'v_daily_usage' => $v,
                'v_day_values' => $vBreakdown['days'],
                'v_day_total' => $vBreakdown['total'],
                'v_day_average' => $vBreakdown['average'],
                'v_window_from' => $vBreakdown['from'],
                'v_window_to' => $vBreakdown['to'],
                'ah_test_amount' => $ah,
                'ai_gap_to_average' => $ai,
                'aj_order_days_expected' => $ajExpected,
                'aj_order_days_stored' => $ajStored,
                'ak_base_qty_expected' => $akExpected,
                'ak_base_qty_stored' => $base,
                'included_in_budget_math' => $isNeedy,
                'coverage' => null,
                'graduated_ma_window' => 'V/AA (budget path uses excel daily usage)',
                'ma_excel_column' => 'V',
                'ma_window_days' => 15,
                'ma_reason' => 'Excel AK uses V (or AA if V is 0) — same rate as AH',
                'rate_source_note' => 'Budget path daily usage = stored V/AA ('.number_format($v, 4).')',
                'implied_rate' => $v > 0 ? $v : null,
                'excel_expected_rate' => $v > 0 ? $v : null,
                'excel_expected_base_qty' => $akExpected,
                'rate_matches_excel' => abs($base - $akExpected) < 0.02 || ($base <= 0 && $akExpected <= 0),
                'af_base_qty' => $base,
                'expected_base_qty' => $akExpected,
                'peak_impact_percent' => $linePeakImpact,
                'qty_after_peak' => $expectedAfterPeak,
                'suggested_qty' => $suggested,
                'order_qty' => $qty,
                'order_days' => $ajStored,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'al_expected' => $alExpected,
                'unscaled_line_total' => $alExpected,
                'skipped_reason' => $skippedReason,
                'matches_af' => abs($base - $akExpected) < 0.02 || ($base <= 0 && $akExpected <= 0),
                'matches_ah_ak' => abs($base - $akExpected) < 0.02
                    && ($ajStored === null || abs($ajStored - $ajExpected) < 0.02),
                'matches_ah_al' => abs($base - $akExpected) < 0.02
                    && ($ajStored === null || abs($ajStored - $ajExpected) < 0.02),
            ];
        }

        $computedPoolDays = ($sumAh > 0 && $ba7 > 0) ? round(15 * $ba7 / $sumAh, 4) : $poolDays;
        $scaleFactor = ($unscaledTotal > 0 && abs($orderTotal - $unscaledTotal) >= 0.01)
            ? round($orderTotal / $unscaledTotal, 6)
            : null;
        $budgetCapUgx = $order->effectiveAmountCap();

        return [
            'method' => $order->orderingTypeLabel(),
            'period_days' => $isAmount ? (float) $computedPoolDays : 0.0,
            'safety_days' => $safety,
            'buffer_days' => $buffer,
            'peak_period_percent' => $peakPeriod,
            'peak_increase_percent' => $peakIncrease,
            'peak_impact_percent' => $peakImpact,
            'budget_mode' => $order->budget_mode,
            'budget_value' => $ba7,
            'order_total' => $orderTotal,
            'unscaled_total' => round($unscaledTotal, 2),
            'scale_factor' => $scaleFactor,
            'budget_cap_ugx' => $budgetCapUgx,
            'ah_sum_test_amount' => round($sumAh, 2),
            'am_average_days_left' => round($avgAm, 4),
            'am_average_count' => $amForAverage->count(),
            'am_sum_for_average' => round($amSumForAverage, 4),
            'ba7_budget_days' => $isAmount ? $computedPoolDays : $ba7,
            'ba7_budget_ugx' => $budgetUgx,
            'ba7_derived_from_amount' => $isAmount,
            'budget_ugx' => $budgetUgx,
            'ah_parts' => $ahParts,
            'am_parts' => $amParts,
            'lines' => $lineRows,
        ];
    }

    /**
     * Excel AF / amount-cap audit.
     *
     * @return array<string, mixed>
     */
    private function calculationBreakdownPeriodOrAmount(InventoryOrder $order): array
    {
        $period = (float) ($order->period_of_order_days ?? 0);
        $safety = (float) ($order->safety_stock_days ?? 0);
        $buffer = (float) ($order->buffer_stock_days ?? 0);
        $peakPeriod = (float) ($order->peak_period_percent ?? 0);
        $peakIncrease = (float) ($order->peak_consumption_increase_percent ?? 0);
        $peakImpact = self::computePeakImpactPercent($peakPeriod, $peakIncrease);
        $orderTotal = $order->orderTotal();

        $unscaledTotal = 0.0;
        $lineRows = [];

        foreach ($order->lines as $line) {
            $m = (float) ($line->current_stock_suom ?? 0);
            $n = $line->stock_days_at_order !== null ? (float) $line->stock_days_at_order : null;
            $am = $line->days_left_at_order !== null ? (float) $line->days_left_at_order : null;
            $v = (float) ($line->daily_average_suom ?? 0);
            $base = (float) ($line->base_suggested_quantity_suom ?? 0);
            $suggested = (float) ($line->suggested_quantity_suom ?? 0);
            $qty = (float) ($line->order_quantity_suom ?? 0);
            $price = (float) ($line->unit_price ?? 0);
            $lineTotal = (float) ($line->line_total ?? 0);
            $linePeakImpact = (float) ($line->peak_impact_percent ?? $peakImpact);

            $stockDaysForCoverage = $n ?? 0.0;
            $coverage = round($period + $safety + $buffer - $stockDaysForCoverage, 4);
            $maSelection = $this->graduatedMaSelection($period);
            $graduatedWindow = $maSelection['window_label'];
            $impliedRate = $coverage > 0 && $base > 0
                ? round($base / $coverage, 4)
                : null;

            $excelExpectedRate = null;
            $excelExpectedBase = null;
            $rateMatchesExcel = true;
            if ($coverage > 0 && $impliedRate !== null) {
                // N always uses V; AF qty uses graduated MA(period).
                // Only when period selects V (< 15) should the qty rate match stored V.
                if ($maSelection['window_days'] === 15 && $v > 0) {
                    $excelExpectedRate = $v;
                    $excelExpectedBase = round($coverage * $v, 4);
                    $rateMatchesExcel = abs($impliedRate - $v) < 0.01;
                } else {
                    $excelExpectedRate = $impliedRate;
                    $excelExpectedBase = $base;
                    $rateMatchesExcel = true;
                }
            }

            $expectedBase = $coverage > 0 && $impliedRate !== null
                ? round($coverage * $impliedRate, 4)
                : 0.0;

            $expectedAfterPeak = $this->applyPeakToSuggestedQuantity($base, $linePeakImpact);

            $unscaledLineTotal = round($expectedAfterPeak * $price, 2);
            $unscaledTotal += $unscaledLineTotal;

            $skippedReason = null;
            if ($coverage <= 0) {
                $skippedReason = 'coverage ≤ 0 (stock days already cover period + safety + buffer)';
            } elseif ($base <= 0 && $qty <= 0) {
                $skippedReason = 'base qty is zero';
            }

            $rateSourceNote = null;
            if ($impliedRate !== null) {
                $rateSourceNote = 'Order qty uses '.$maSelection['window_label']
                    .' from period of order ('.$period.' days). '
                    .'Stock days N still use V/AA (15-day) = '.number_format($v, 4).'.';
            }

            $vBreakdown = $this->fifteenDayUsageBreakdown(
                (int) $order->business_id,
                (int) $order->store_id,
                (int) $line->item_id,
                Carbon::parse($order->created_at ?? now())
            );

            $lineRows[] = [
                'item_id' => $line->item_id,
                'item_name' => $line->item?->name ?? '—',
                'item_code' => $line->item?->code,
                'suom' => $line->item?->itemUnit?->name,
                'm_current_stock' => $m,
                'n_stock_days' => $n,
                'am_days_left' => $am,
                'v_daily_usage' => $v,
                'v_day_values' => $vBreakdown['days'],
                'v_day_total' => $vBreakdown['total'],
                'v_day_average' => $vBreakdown['average'],
                'v_window_from' => $vBreakdown['from'],
                'v_window_to' => $vBreakdown['to'],
                'coverage' => $coverage,
                'graduated_ma_window' => $graduatedWindow,
                'ma_excel_column' => $maSelection['excel_column'],
                'ma_window_days' => $maSelection['window_days'],
                'ma_reason' => $maSelection['reason'],
                'rate_source_note' => $rateSourceNote,
                'implied_rate' => $impliedRate,
                'excel_expected_rate' => $excelExpectedRate,
                'excel_expected_base_qty' => $excelExpectedBase,
                'rate_matches_excel' => $rateMatchesExcel,
                'af_base_qty' => $base,
                'expected_base_qty' => $expectedBase,
                'peak_impact_percent' => $linePeakImpact,
                'qty_after_peak' => $expectedAfterPeak,
                'suggested_qty' => $suggested,
                'order_qty' => $qty,
                'order_days' => $line->order_days !== null ? (float) $line->order_days : null,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'unscaled_line_total' => $unscaledLineTotal,
                'skipped_reason' => $skippedReason,
                'matches_af' => abs($base - $expectedBase) < 0.01 || ($base <= 0 && $coverage <= 0),
            ];
        }

        $scaleFactor = null;
        if ($unscaledTotal > 0 && abs($orderTotal - $unscaledTotal) >= 0.01) {
            $scaleFactor = round($orderTotal / $unscaledTotal, 6);
        }

        return [
            'method' => $order->orderingTypeLabel(),
            'period_days' => $period,
            'safety_days' => $safety,
            'buffer_days' => $buffer,
            'peak_period_percent' => $peakPeriod,
            'peak_increase_percent' => $peakIncrease,
            'peak_impact_percent' => $peakImpact,
            'budget_mode' => $order->budget_mode,
            'budget_value' => $order->budget_value !== null ? (float) $order->budget_value : null,
            'order_total' => $orderTotal,
            'unscaled_total' => round($unscaledTotal, 2),
            'scale_factor' => $scaleFactor,
            'ah_sum_test_amount' => null,
            'am_average_days_left' => null,
            'ba7_budget_days' => null,
            'lines' => $lineRows,
        ];
    }

    /**
     * Daily consumption values that make up Excel V (15-day MA).
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     total: float,
     *     average: float,
     *     days: list<array{date: string, quantity: float}>
     * }
     */
    private function fifteenDayUsageBreakdown(int $businessId, int $storeId, int $itemId, Carbon $asOf): array
    {
        $to = $asOf->copy()->startOfDay();
        $from = $to->copy()->subDays(14);

        $byDate = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $itemId)
            ->whereIn('source', InventoryDailyConsumption::demandSources())
            ->whereDate('consumption_date', '>=', $from->toDateString())
            ->whereDate('consumption_date', '<=', $to->toDateString())
            ->pluck('quantity_suom', 'consumption_date')
            ->mapWithKeys(function ($qty, $date) {
                return [Carbon::parse($date)->toDateString() => (float) $qty];
            });

        $days = [];
        $total = 0.0;

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $qty = (float) ($byDate[$key] ?? 0);
            $total += $qty;
            $days[] = [
                'date' => $key,
                'quantity' => $qty,
            ];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => round($total, 4),
            'average' => round($total / 15, 4),
            'days' => $days,
        ];
    }

    private function graduatedMaWindowLabel(?float $stockDaysN): string
    {
        return $this->graduatedMaSelection($stockDaysN)['window_label'];
    }

    /**
     * Excel AF graduated MA picker from period of order (BA6):
     * V if period < 15; W if < 30; X if < 90; Y if < 180; Z otherwise.
     *
     * @return array{
     *     excel_column: string,
     *     window_days: int,
     *     window_label: string,
     *     reason: string,
     *     n_value: float
     * }
     */
    private function graduatedMaSelection(?float $periodDays): array
    {
        $period = $periodDays ?? 0.0;

        $map = [
            15 => ['excel_column' => 'V', 'name' => '15-day MA'],
            30 => ['excel_column' => 'W', 'name' => '30-day MA'],
            90 => ['excel_column' => 'X', 'name' => '90-day MA'],
            180 => ['excel_column' => 'Y', 'name' => '180-day MA'],
            360 => ['excel_column' => 'Z', 'name' => '360-day MA'],
        ];

        foreach ($map as $days => $meta) {
            if ($period < $days) {
                $periodDisplay = $periodDays === null
                    ? '0 (period missing → treated as 0)'
                    : number_format($period, 1);

                return [
                    'excel_column' => $meta['excel_column'],
                    'window_days' => $days,
                    'window_label' => $meta['name'].' ('.$meta['excel_column'].')',
                    'reason' => 'Period = '.$periodDisplay.' days, and period < '.$days
                        .' → Excel AF uses column '.$meta['excel_column'].' ('.$meta['name'].')',
                    'n_value' => $period,
                ];
            }
        }

        return [
            'excel_column' => 'Z',
            'window_days' => 360,
            'window_label' => '360-day MA (Z)',
            'reason' => 'Period = '.number_format($period, 1).' days, and period ≥ 360 → Excel AF uses column Z (360-day MA)',
            'n_value' => $period,
        ];
    }

    /**
     * Peak impact (%) = peak period (%) × consumption increase (%) ÷ 100.
     */
    public static function computePeakImpactPercent(?float $peakPeriodPercent, ?float $consumptionIncreasePercent): float
    {
        $peakPeriod = max(0, (float) ($peakPeriodPercent ?? 0));
        $increase = max(0, (float) ($consumptionIncreasePercent ?? 0));

        if ($peakPeriod <= 0 || $increase <= 0) {
            return 0.0;
        }

        return round($peakPeriod * $increase / 100, 4);
    }

    public function applyPeakToSuggestedQuantity(float $baseSuggested, float $peakImpactPercent): float
    {
        return max(0, round($baseSuggested * (1 + ($peakImpactPercent / 100)), 4));
    }

    /**
     * @return array{
     *     line: InventoryOrderLine,
     *     redistributed: bool,
     *     adjusted_count: int,
     *     comparison: ?array{
     *         edited_line_id: int,
     *         cap: float,
     *         order_total_before: float,
     *         order_total_after: float,
     *         lines: list<array<string, mixed>>
     *     }
     * }
     */
    public function updateLinePeakIncrease(InventoryOrderLine $line, float $consumptionIncreasePercent): array
    {
        $line->loadMissing(['order', 'item']);
        $baseSuggested = (float) ($line->base_suggested_quantity_suom ?? $line->suggested_quantity_suom);
        $peakImpact = self::computePeakImpactPercent($line->order->peak_period_percent, $consumptionIncreasePercent);
        $suggested = $this->applyPeakToSuggestedQuantity($baseSuggested, $peakImpact);

        $line->update([
            'peak_consumption_increase_percent' => max(0, $consumptionIncreasePercent),
            'peak_impact_percent' => $peakImpact,
        ]);

        $result = $this->applyLineQuantityUpdate($line->fresh(['order', 'item']), $suggested, null, true);
        $updated = $result['line'];
        $updated->update([
            'suggested_quantity_suom' => (float) $updated->order_quantity_suom,
        ]);

        $result['line'] = $updated->fresh('item');

        return $result;
    }

    private function createOrderLine(
        InventoryOrder $order,
        Item $item,
        float $baseSuggested,
        float $dailyAvg,
        float $arStock,
        float $currentStock,
        ?float $stockDays,
        ?float $daysLeft,
        ?float $orderDays,
        float $unitPrice,
        float $consumptionIncreasePercent
    ): void {
        $peakImpact = self::computePeakImpactPercent($order->peak_period_percent, $consumptionIncreasePercent);
        $suggested = $this->applyPeakToSuggestedQuantity($baseSuggested, $peakImpact);

        InventoryOrderLine::create([
            'inventory_order_id' => $order->id,
            'item_id' => $item->id,
            'supplier_id' => $order->supplier_id,
            'daily_average_suom' => $dailyAvg,
            'lead_time_days' => $this->averageLeadTimeDays((int) $order->business_id, (int) $item->id),
            'system_quantity_suom' => $arStock,
            'current_stock_suom' => $currentStock,
            'stock_days_at_order' => $stockDays,
            'days_left_at_order' => $daysLeft,
            'order_days' => $orderDays,
            'base_suggested_quantity_suom' => $baseSuggested,
            'peak_consumption_increase_percent' => max(0, $consumptionIncreasePercent),
            'peak_impact_percent' => $peakImpact,
            'suggested_quantity_suom' => $suggested,
            'order_quantity_suom' => $suggested,
            'order_quantity_ouom' => $this->toOuom($item, $suggested),
            'unit_price' => $unitPrice,
            'line_total' => round($suggested * $unitPrice, 2),
        ]);
    }

    public function explainEmptyOrder(InventoryOrder $order): string
    {
        if (! empty($order->item_ids)) {
            $selectedCount = count($order->item_ids);

            return "No order items were generated for the {$selectedCount} selected item(s). Check that they are goods with consumption or stock at this store, then refresh items.";
        }

        $stockCount = InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->count();

        if ($stockCount === 0) {
            return 'No items have stock or consumption history at this store. Receive goods via a goods receive note or wait for sale consumption, then refresh items.';
        }

        if ($order->importance_filter) {
            $label = ItemImportanceCategory::labelForSlug((int) $order->business_id, $order->importance_filter) ?? $order->importance_filter;

            $matchingStock = InventoryStockLevel::query()
                ->where('business_id', $order->business_id)
                ->where('store_id', $order->store_id)
                ->where(function ($query) {
                    $query->where('quantity_suom', '>', 0)
                        ->orWhere('ma_15_days', '>', 0);
                })
                ->whereHas('item', fn ($query) => $query->where('importance_category', $order->importance_filter))
                ->count();

            if ($matchingStock === 0) {
                $uncategorizedStock = InventoryStockLevel::query()
                    ->where('business_id', $order->business_id)
                    ->where('store_id', $order->store_id)
                    ->where('quantity_suom', '>', 0)
                    ->whereHas('item', fn ($query) => $query->whereNull('importance_category'))
                    ->count();

                if ($uncategorizedStock > 0) {
                    return "This order filters to {$label} items only, but {$uncategorizedStock} stocked item(s) have no importance category. Make an order with \"All items\", or set categories on your goods under Items.";
                }

                return "No stocked items at this store match the {$label} filter.";
            }
        }

        return 'Refresh lines to repopulate from current stock and moving averages.';
    }

    public function applyBudgetConstraints(InventoryOrder $order, ?InventoryModuleConfig $config = null): void
    {
        // Excel budget path (AH–AL / BA7 days) does not scale to a UGX cap.
        // Optional UGX cap enforcement uses initial_order_total via applyAmountCapConstraints.
    }

    public function applyAmountCapConstraints(InventoryOrder $order): void
    {
        $cap = $order->effectiveAmountCap();

        if ($cap === null || $cap <= 0) {
            return;
        }

        $this->scaleLinesToAmountCap($order, $cap);
    }

    private function scaleLinesToAmountCap(InventoryOrder $order, float $cap): void
    {
        $order->load('lines');
        $total = $order->orderTotal();

        if ($total <= 0 || $cap <= 0) {
            return;
        }

        if (abs($total - $cap) < 0.01) {
            return;
        }

        $factor = $cap / $total;

        foreach ($order->lines as $line) {
            $qty = round((float) $line->order_quantity_suom * $factor, 4);
            $unitPrice = (float) ($line->unit_price ?? 0);

            $line->update([
                'order_quantity_suom' => max(0, $qty),
                'order_quantity_ouom' => $line->item ? $this->toOuom($line->item, $qty) : null,
                'suggested_quantity_suom' => max(0, $qty),
                'line_total' => round(max(0, $qty) * $unitPrice, 2),
            ]);
        }
    }

    public function averageLeadTimeDays(int $businessId, int $itemId): int
    {
        $avg = GoodsReceivedNoteLine::query()
            ->join('goods_received_notes as grn', 'grn.id', '=', 'goods_received_note_lines.goods_received_note_id')
            ->where('grn.business_id', $businessId)
            ->where('grn.status', GoodsReceivedNote::STATUS_APPROVED)
            ->where('goods_received_note_lines.item_id', $itemId)
            ->avg('grn.lead_time_days');

        return max(0, (int) round((float) ($avg ?? 0)));
    }

    /**
     * @return array{
     *     line: InventoryOrderLine,
     *     redistributed: bool,
     *     adjusted_count: int,
     *     comparison: ?array{
     *         edited_line_id: int,
     *         cap: ?float,
     *         capped: bool,
     *         order_total_before: float,
     *         order_total_after: float,
     *         lines: list<array<string, mixed>>
     *     }
     * }
     */
    public function applyLineQuantityUpdate(
        InventoryOrderLine $line,
        float $orderQtySuom,
        ?float $orderQtyOuom = null,
        bool $redistributeWhenCapped = true
    ): array {
        $line->loadMissing(['order.lines.item', 'item']);
        $order = $line->order;
        $unitPrice = (float) ($line->unit_price ?? 0);
        $orderQtySuom = max(0, $orderQtySuom);
        $beforeSnapshot = $this->snapshotOrderLinesForCapDiff($order);
        $orderTotalBefore = round(array_sum(array_column($beforeSnapshot, 'total')), 2);
        $enforceCap = $redistributeWhenCapped && $order->enforcesBudgetCap() && $unitPrice > 0;

        if (! $enforceCap) {
            if ($orderQtyOuom === null && $line->item) {
                $orderQtyOuom = $this->toOuom($line->item, $orderQtySuom);
            }

            $line->update([
                'order_quantity_suom' => $orderQtySuom,
                'order_quantity_ouom' => $orderQtyOuom,
                'line_total' => round($orderQtySuom * $unitPrice, 2),
            ]);

            $this->syncOrderDaysFromQuantity($line->fresh(['order', 'item']), $orderQtySuom);

            $freshOrder = $order->fresh(['lines.item']);
            $cap = $order->effectiveAmountCap();

            return [
                'line' => $line->fresh('item'),
                'redistributed' => false,
                'adjusted_count' => 0,
                'comparison' => $this->buildCapAdjustmentComparison(
                    $beforeSnapshot,
                    $freshOrder,
                    (int) $line->id,
                    $cap !== null ? (float) $cap : null,
                    $orderTotalBefore,
                    false
                ),
            ];
        }

        $cap = (float) $order->effectiveAmountCap();
        $oldLineTotal = (float) ($line->line_total ?? 0);
        $requestedTotal = round($orderQtySuom * $unitPrice, 2);
        $newLineTotal = min($requestedTotal, $cap);
        $newQty = round($newLineTotal / $unitPrice, 4);

        if ($orderQtyOuom === null && $line->item) {
            $orderQtyOuom = $this->toOuom($line->item, $newQty);
        }

        $line->update([
            'order_quantity_suom' => $newQty,
            'order_quantity_ouom' => $orderQtyOuom,
            'line_total' => $newLineTotal,
        ]);

        $this->syncOrderDaysFromQuantity($line->fresh(['order', 'item']), $newQty);

        $delta = round($newLineTotal - $oldLineTotal, 2);
        $adjustedCount = 0;

        if (abs($delta) >= 0.01) {
            $adjustedCount = $this->redistributeBudgetDeltaEqually(
                $order->fresh(['lines.item']),
                $line->fresh(),
                $delta
            );
        }

        $this->reconcileOrderTotalToCap($order->fresh(['lines.item']), $cap);

        $freshOrder = $order->fresh(['lines.item']);
        $comparison = $this->buildCapAdjustmentComparison(
            $beforeSnapshot,
            $freshOrder,
            (int) $line->id,
            $cap,
            $orderTotalBefore,
            true
        );

        return [
            'line' => $line->fresh('item'),
            'redistributed' => $adjustedCount > 0 || collect($comparison['lines'])->contains(
                fn (array $row) => $row['changed']
            ),
            'adjusted_count' => $adjustedCount,
            'comparison' => $comparison,
        ];
    }

    /**
     * Days Mode: order_qty = MA × days − on_hand.
     *
     * @return array{
     *     line: InventoryOrderLine,
     *     redistributed: bool,
     *     adjusted_count: int,
     *     comparison: ?array
     * }
     */
    public function applyLineDaysUpdate(
        InventoryOrderLine $line,
        float $orderDays,
        bool $redistributeWhenCapped = true
    ): array {
        $line->loadMissing(['order', 'item']);
        $order = $line->order;
        $orderDays = max(0, $orderDays);

        $basis = $order->usesDemandForecast()
            ? InventoryDaysOfStockService::FORECAST_DEMAND
            : InventoryDaysOfStockService::FORECAST_CONSUMPTION;

        $window = $this->daysOfStock->forecastWindowDays($orderDays > 0 ? $orderDays : 15);
        $ma = $this->daysOfStock->movingAverageDaily(
            (int) $order->business_id,
            (int) $order->store_id,
            (int) $line->item_id,
            $window,
            $basis
        );

        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->where('item_id', $line->item_id)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->value('quantity_suom') ?? $line->current_stock_suom ?? 0);

        $qty = max(0, round(($ma * $orderDays) - $onHand, 4));

        $result = $this->applyLineQuantityUpdate($line, $qty, null, $redistributeWhenCapped);
        $result['line']->update(['order_days' => $orderDays]);
        $result['line'] = $result['line']->fresh('item');

        return $result;
    }

    /**
     * Reverse of Days Mode: order_days ≈ (qty + on_hand) / MA.
     */
    private function syncOrderDaysFromQuantity(InventoryOrderLine $line, float $orderQtySuom): void
    {
        $line->loadMissing(['order', 'item']);
        $order = $line->order;
        if (! $order || ! $order->store_id) {
            return;
        }

        $basis = $order->usesDemandForecast()
            ? InventoryDaysOfStockService::FORECAST_DEMAND
            : InventoryDaysOfStockService::FORECAST_CONSUMPTION;

        $ma = $this->daysOfStock->movingAverageDaily(
            (int) $order->business_id,
            (int) $order->store_id,
            (int) $line->item_id,
            15,
            $basis
        );

        if ($ma <= 0) {
            $line->update(['order_days' => null]);

            return;
        }

        $onHand = (float) (InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->where('item_id', $line->item_id)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->value('quantity_suom') ?? $line->current_stock_suom ?? 0);

        $days = round(($orderQtySuom + $onHand) / $ma, 2);
        $line->update(['order_days' => max(0, $days)]);
    }

    /**
     * Daily usage for an order line using consumption MA or demand ledger MA.
     */
    private function dailyUsageForOrder(
        InventoryOrder $order,
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        int $windowDays = 15
    ): float {
        if ($order->usesDemandForecast()) {
            $ma = $this->daysOfStock->movingAverageDaily(
                (int) $order->business_id,
                (int) $order->store_id,
                (int) $stock->item_id,
                $windowDays,
                InventoryDaysOfStockService::FORECAST_DEMAND
            );

            if ($ma > 0) {
                return $ma;
            }

            return (float) ($config?->fixed_daily_average_suom ?? 0);
        }

        return $this->analytics->excelDailyUsageSuom($stock, $config);
    }

    private function periodRateForOrder(
        InventoryOrder $order,
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        float $periodDays
    ): float {
        if ($order->usesDemandForecast()) {
            return $this->dailyUsageForOrder(
                $order,
                $stock,
                $config,
                $this->daysOfStock->forecastWindowDays($periodDays)
            );
        }

        return $this->analytics->periodOrderDailyRate($stock, $config, $periodDays);
    }

    private function stockDaysForOrder(
        InventoryOrder $order,
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config
    ): ?float {
        $usage = $this->dailyUsageForOrder($order, $stock, $config, 15);

        if ($usage <= 0) {
            return null;
        }

        return round($this->analytics->currentStockLevelSuom($stock) / $usage, 1);
    }

    private function suggestedQtyPeriodForOrder(
        InventoryOrder $order,
        InventoryStockLevel $stock,
        ?InventoryModuleConfig $config,
        float $periodDays
    ): float {
        if (! $order->usesDemandForecast()) {
            return $this->analytics->suggestedOrderQtyPeriod($stock, $config, $periodDays, $order);
        }

        $stockDays = $this->stockDaysForOrder($order, $stock, $config) ?? 0;
        $coverage = $periodDays
            + $this->analytics->safetyStockDays($stock, $config, $order)
            + $this->analytics->bufferStockDays($stock, $config, $order)
            - $stockDays;

        if ($coverage <= 0) {
            return 0.0;
        }

        $rate = $this->periodRateForOrder($order, $stock, $config, $periodDays);

        return max(0, round($coverage * $rate, 4));
    }

    /**
     * @return array<int, array{item_name: string, item_code: ?string, qty: float, total: float, unit_price: float}>
     */
    private function snapshotOrderLinesForCapDiff(InventoryOrder $order): array
    {
        $order->loadMissing('lines.item');

        $snapshot = [];
        foreach ($order->lines as $orderLine) {
            $snapshot[(int) $orderLine->id] = [
                'item_name' => $orderLine->item?->name ?? '—',
                'item_code' => $orderLine->item?->code,
                'qty' => (float) ($orderLine->order_quantity_suom ?? 0),
                'total' => (float) ($orderLine->line_total ?? 0),
                'unit_price' => (float) ($orderLine->unit_price ?? 0),
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<int, array{item_name: string, item_code: ?string, qty: float, total: float, unit_price: float}>  $before
     * @return array{
     *     edited_line_id: int,
     *     cap: ?float,
     *     capped: bool,
     *     order_total_before: float,
     *     order_total_after: float,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function buildCapAdjustmentComparison(
        array $before,
        InventoryOrder $afterOrder,
        int $editedLineId,
        ?float $cap,
        float $orderTotalBefore,
        bool $capped = true
    ): array {
        $afterOrder->loadMissing('lines.item');
        $rows = [];

        foreach ($afterOrder->lines as $orderLine) {
            $id = (int) $orderLine->id;
            $beforeRow = $before[$id] ?? [
                'item_name' => $orderLine->item?->name ?? '—',
                'item_code' => $orderLine->item?->code,
                'qty' => 0.0,
                'total' => 0.0,
                'unit_price' => (float) ($orderLine->unit_price ?? 0),
            ];
            $qtyAfter = (float) ($orderLine->order_quantity_suom ?? 0);
            $totalAfter = (float) ($orderLine->line_total ?? 0);
            $qtyDelta = round($qtyAfter - (float) $beforeRow['qty'], 4);
            $totalDelta = round($totalAfter - (float) $beforeRow['total'], 2);
            $changed = abs($qtyDelta) >= 0.0001 || abs($totalDelta) >= 0.01;

            $rows[] = [
                'line_id' => $id,
                'item_name' => $beforeRow['item_name'],
                'item_code' => $beforeRow['item_code'],
                'role' => $id === $editedLineId ? 'edited' : ($changed ? 'adjusted' : 'unchanged'),
                'qty_before' => (float) $beforeRow['qty'],
                'qty_after' => $qtyAfter,
                'qty_delta' => $qtyDelta,
                'total_before' => (float) $beforeRow['total'],
                'total_after' => $totalAfter,
                'total_delta' => $totalDelta,
                'changed' => $changed,
            ];
        }

        usort($rows, function (array $a, array $b) use ($editedLineId) {
            if ($a['line_id'] === $editedLineId) {
                return -1;
            }
            if ($b['line_id'] === $editedLineId) {
                return 1;
            }
            if ($a['changed'] !== $b['changed']) {
                return $a['changed'] ? -1 : 1;
            }

            return strcmp($a['item_name'], $b['item_name']);
        });

        return [
            'edited_line_id' => $editedLineId,
            'cap' => $cap,
            'capped' => $capped,
            'order_total_before' => $orderTotalBefore,
            'order_total_after' => $afterOrder->orderTotal(),
            'lines' => $rows,
        ];
    }

    public function updateLine(InventoryOrderLine $line, float $orderQtySuom, ?float $orderQtyOuom = null): InventoryOrderLine
    {
        return $this->applyLineQuantityUpdate($line, $orderQtySuom, $orderQtyOuom, true)['line'];
    }

    public function setOrderSupplier(InventoryOrder $order, int $supplierId): InventoryOrder
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier can only be changed on draft purchase requests.',
            ]);
        }

        Supplier::query()
            ->where('business_id', (int) $order->business_id)
            ->whereKey($supplierId)
            ->firstOrFail();

        $order->update(['supplier_id' => $supplierId]);

        if ($order->lines()->exists()) {
            $this->populateLines($order->fresh());
        }

        $order = $order->fresh(['supplier', 'lines.item']);
        $this->refreshRfqDocument($order);

        return $order->fresh(['supplier', 'lines.item']);
    }

    public function setBudgetCapEnforced(InventoryOrder $order, bool $enforced): InventoryOrder
    {
        $order->update(['budget_cap_enforced' => $enforced]);

        if ($enforced) {
            $this->applyAmountCapConstraints($order->fresh(['lines']));
        }

        return $order->fresh(['lines']);
    }

    public function constrainLineQuantityToBudget(InventoryOrderLine $line, float $requestedQtySuom): float
    {
        $line->loadMissing('order');
        $order = $line->order;

        if (! $order->enforcesBudgetCap()) {
            return max(0, $requestedQtySuom);
        }

        $unitPrice = (float) ($line->unit_price ?? 0);

        if ($unitPrice <= 0) {
            return max(0, $requestedQtySuom);
        }

        $budgetCap = (float) $order->effectiveAmountCap();
        $maxQty = floor(($budgetCap / $unitPrice) * 10000) / 10000;

        return max(0, min($requestedQtySuom, $maxQty));
    }

    /**
     * Split a UGX delta equally across other priced lines so order total stays at the cap.
     * Positive delta = edited line grew → reduce others. Negative = edited line shrank → increase others.
     */
    private function redistributeBudgetDeltaEqually(
        InventoryOrder $order,
        InventoryOrderLine $editedLine,
        float $deltaUgx
    ): int {
        $pool = $order->lines
            ->filter(fn (InventoryOrderLine $line) => (int) $line->id !== (int) $editedLine->id
                && (float) ($line->unit_price ?? 0) > 0)
            ->values();

        if ($pool->isEmpty() || abs($deltaUgx) < 0.01) {
            return 0;
        }

        $remaining = $deltaUgx;
        $adjusted = 0;

        for ($pass = 0; $pass < 25 && abs($remaining) >= 0.01 && $pool->isNotEmpty(); $pass++) {
            $share = $remaining / $pool->count();
            $nextPool = collect();
            $progress = 0.0;

            foreach ($pool as $line) {
                $price = (float) $line->unit_price;
                $oldTotal = (float) $line->line_total;
                $targetTotal = round($oldTotal - $share, 2);

                if ($share > 0 && $targetTotal < 0) {
                    $targetTotal = 0.0;
                }

                $applied = round($oldTotal - $targetTotal, 2);
                $progress += $applied;
                $remaining = round($remaining - $applied, 2);

                $qty = round($targetTotal / $price, 4);
                $line->update([
                    'order_quantity_suom' => max(0, $qty),
                    'order_quantity_ouom' => $line->item ? $this->toOuom($line->item, $qty) : null,
                    'line_total' => max(0, $targetTotal),
                ]);
                $adjusted++;

                if ($targetTotal > 0.009) {
                    $nextPool->push($line->fresh('item'));
                }
            }

            if ($share < 0) {
                break;
            }

            if (abs($progress) < 0.01) {
                break;
            }

            $pool = $nextPool->values();
        }

        return $adjusted;
    }

    private function reconcileOrderTotalToCap(InventoryOrder $order, float $cap): void
    {
        $total = $order->orderTotal();
        $diff = round($cap - $total, 2);

        if (abs($diff) < 0.01) {
            return;
        }

        $candidate = $order->lines
            ->filter(fn (InventoryOrderLine $line) => (float) ($line->unit_price ?? 0) > 0)
            ->sortByDesc(fn (InventoryOrderLine $line) => (float) $line->line_total)
            ->first();

        if (! $candidate) {
            return;
        }

        $price = (float) $candidate->unit_price;
        $newTotal = max(0, round((float) $candidate->line_total + $diff, 2));
        $qty = round($newTotal / $price, 4);

        $candidate->update([
            'order_quantity_suom' => max(0, $qty),
            'order_quantity_ouom' => $candidate->item ? $this->toOuom($candidate->item, $qty) : null,
            'line_total' => $newTotal,
        ]);
    }

    private function toOuom(Item $item, float $orderQtySuom): ?float
    {
        if ($item->suom_per_ouom && (float) $item->suom_per_ouom > 0) {
            return round($orderQtySuom / (float) $item->suom_per_ouom, 4);
        }

        return null;
    }

    private function itemPassesOrderFilters(Item $item, InventoryOrder $order): bool
    {
        if ($item->type !== 'good') {
            return false;
        }

        if (! empty($order->item_ids)) {
            return in_array((int) $item->id, array_map('intval', $order->item_ids), true);
        }

        if ($order->importance_filter && $item->importance_category !== $order->importance_filter) {
            return false;
        }

        if ($order->group_id && (int) $item->group_id !== (int) $order->group_id) {
            return false;
        }

        if ($order->subgroup_id && (int) $item->subgroup_id !== (int) $order->subgroup_id) {
            return false;
        }

        // External RFQs are multi-supplier: do not filter items by a header supplier.
        // Supplier selection happens later during quotation analysis / LPO split.

        return true;
    }

    private function shouldKeepSelectedItem(InventoryOrder $order, Item $item): bool
    {
        if (empty($order->item_ids)) {
            return false;
        }

        return in_array((int) $item->id, array_map('intval', $order->item_ids), true);
    }

    private function storedPeriodOfOrderDays(
        ?string $budgetMode,
        ?float $periodOfOrderDays,
        ?InventoryModuleConfig $config
    ): ?float {
        if (in_array($budgetMode, [
            InventoryOrder::BUDGET_MODE_DAYS,
            InventoryOrder::BUDGET_MODE_AMOUNT,
        ], true)) {
            return null;
        }

        if ($periodOfOrderDays !== null) {
            return (float) $periodOfOrderDays;
        }

        return $config?->period_of_order_days !== null
            ? (float) $config->period_of_order_days
            : null;
    }

    private function periodDaysForCalculation(InventoryOrder $order, ?InventoryModuleConfig $config): float
    {
        if ($order->period_of_order_days !== null && (float) $order->period_of_order_days > 0) {
            return (float) $order->period_of_order_days;
        }

        return max(0, (float) ($config?->period_of_order_days ?? 0));
    }

    /**
     * @param  array<int|string>|null  $itemIds
     * @return array<int, int>|null
     */
    private function normalizeItemIds(?array $itemIds): ?array
    {
        if ($itemIds === null || $itemIds === []) {
            return null;
        }

        $normalized = array_values(array_unique(array_map('intval', $itemIds)));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return Collection<int, InventoryStockLevel>
     */
    private function stockLevelsForOrder(InventoryOrder $order): Collection
    {
        $itemIds = $this->normalizeItemIds($order->item_ids);

        $query = InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->with(['item.itemUnit', 'item.orderUnit', 'item.suppliers']);

        if ($itemIds === null) {
            $query->where(function ($subQuery) {
                $subQuery->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            });

            return $query->get();
        }

        $levels = $query
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        $items = Item::query()
            ->where('business_id', $order->business_id)
            ->where('type', 'good')
            ->whereIn('id', $itemIds)
            ->with(['itemUnit', 'orderUnit', 'suppliers'])
            ->get();

        return $items->map(function (Item $item) use ($order, $levels): InventoryStockLevel {
            if ($levels->has($item->id)) {
                return $levels->get($item->id);
            }

            return $this->emptyStockLevel($order, $item);
        })->values();
    }

    private function emptyStockLevel(InventoryOrder $order, Item $item): InventoryStockLevel
    {
        $level = new InventoryStockLevel([
            'business_id' => $order->business_id,
            'store_id' => $order->store_id,
            'item_id' => $item->id,
            'quantity_suom' => 0,
            'ma_15_days' => 0,
            'ma_30_days' => 0,
        ]);
        $level->setRelation('item', $item);

        return $level;
    }
}
