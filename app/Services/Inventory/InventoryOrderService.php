<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\ItemImportanceCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOrderService
{
    /** Consumption rate window — auto-applied (15-day MA per Excel V/AA). */
    public const AUTO_CONSUMPTION_RATE_DAYS = 15;

    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics
    ) {}

    public function generateOrderNumber(int $businessId): string
    {
        $prefix = 'RFQ-'.now()->format('Ymd');
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
    ): InventoryOrder {
        $normalizedItemIds = $this->normalizeItemIds($itemIds);

        return DB::transaction(function () use ($businessId, $storeId, $user, $importanceFilter, $budgetMode, $budgetValue, $periodOfOrderDays, $notes, $groupId, $subgroupId, $peakPeriodPercent, $peakConsumptionIncreasePercent, $safetyStockDays, $bufferStockDays, $notificationToOrderDays, $normalizedItemIds, $supplierId, $orderType, $sourceStoreId) {
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
                'order_number' => $this->generateOrderNumber($businessId),
                'status' => InventoryOrder::STATUS_DRAFT,
                'importance_filter' => $importanceFilter,
                'group_id' => $groupId,
                'subgroup_id' => $subgroupId,
                'item_ids' => $normalizedItemIds,
                'budget_mode' => $budgetMode,
                'budget_value' => $budgetValue,
                'moving_average_days' => self::AUTO_CONSUMPTION_RATE_DAYS,
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

        $periodDays = $this->periodDaysForCalculation($order, $config);
        $peakIncrease = max(0, (float) ($order->peak_consumption_increase_percent ?? 0));

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            $dailyAvg = $this->analytics->excelDailyUsageSuom($stock, $config);

            if ($dailyAvg <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $baseSuggested = $this->analytics->suggestedOrderQtyPeriod($stock, $config, $periodDays, $order);

            if ($baseSuggested <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $arStock = $this->analytics->systemStockArSuom($stock, $config);
            $currentStock = $this->analytics->currentStockLevelSuom($stock);
            $stockDays = $this->analytics->stockDaysReport($stock, $config);
            $daysLeft = $this->analytics->daysLeftToOrder($stock, $config, $order);
            $unitPrice = $this->analytics->purchasePricePerSuom($stock, $item);

            $this->createOrderLine(
                $order,
                $item,
                $baseSuggested,
                $dailyAvg,
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
        $total = $order->orderTotal();

        if ($total > 0) {
            $order->update(['initial_order_total' => $total]);
        }
    }

    /**
     * Excel budget path AH–AL: proportional order days from a stock-days budget (BA7),
     * weighted by days left to order (AM) per item.
     *
     * @param  Collection<int, InventoryStockLevel>  $stockLevels
     */
    private function populateBudgetDaysLines(
        InventoryOrder $order,
        Collection $stockLevels,
        ?InventoryModuleConfig $config,
        ?float $budgetDaysOverride = null
    ): void {
        $budgetDays = $budgetDaysOverride !== null
            ? min(366, max(1, (int) round($budgetDaysOverride)))
            : min(366, max(1, (int) round((float) $order->budget_value)));
        $peakIncrease = max(0, (float) ($order->peak_consumption_increase_percent ?? 0));
        $rows = [];

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            $dailyAvg = $this->analytics->excelDailyUsageSuom($stock, $config);

            if ($dailyAvg <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $daysLeft = $this->analytics->daysLeftToOrder($stock, $config, $order);

            if ($daysLeft === null && ! $this->shouldKeepSelectedItem($order, $item)) {
                continue;
            }

            $testAmount = $this->analytics->budgetTestAmountUgx($stock, $config, $item);

            if ($testAmount <= 0 && ! $this->shouldKeepSelectedItem($order, $item)) {
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
                'stock_days' => $this->analytics->stockDaysReport($stock, $config),
            ];
        }

        if ($rows === []) {
            return;
        }

        $avgDaysLeft = collect($rows)->avg('days_left');
        $sumTestAmount = collect($rows)->sum('test_amount');

        foreach ($rows as $row) {
            $orderDays = $this->analytics->orderDaysBudgetAllocation(
                (float) $budgetDays,
                $row['days_left'],
                (float) $avgDaysLeft,
                (float) $sumTestAmount
            );
            $baseSuggested = $this->analytics->suggestedOrderQtyBudgetDays(
                (float) $budgetDays,
                $row['days_left'],
                (float) $avgDaysLeft,
                (float) $sumTestAmount,
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

    public function updateLinePeakIncrease(InventoryOrderLine $line, float $consumptionIncreasePercent): InventoryOrderLine
    {
        $line->loadMissing(['order', 'item']);
        $baseSuggested = (float) ($line->base_suggested_quantity_suom ?? $line->suggested_quantity_suom);
        $peakImpact = self::computePeakImpactPercent($line->order->peak_period_percent, $consumptionIncreasePercent);
        $suggested = $this->applyPeakToSuggestedQuantity($baseSuggested, $peakImpact);
        $suggested = $this->constrainLineQuantityToBudget($line, $suggested);
        $unitPrice = (float) ($line->unit_price ?? 0);

        $line->update([
            'peak_consumption_increase_percent' => max(0, $consumptionIncreasePercent),
            'peak_impact_percent' => $peakImpact,
            'suggested_quantity_suom' => $suggested,
            'order_quantity_suom' => $suggested,
            'order_quantity_ouom' => $line->item ? $this->toOuom($line->item, $suggested) : null,
            'line_total' => round($suggested * $unitPrice, 2),
        ]);

        return $line->fresh('item');
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
        if (! $order->budget_mode || ! $order->budget_value || $order->budget_value <= 0) {
            return;
        }

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_DAYS) {
            return;
        }

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_AMOUNT) {
            $this->scaleLinesToAmountCap($order, (float) $order->budget_value);
        }
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

        if ($total <= $cap) {
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

    public function updateLine(InventoryOrderLine $line, float $orderQtySuom, ?float $orderQtyOuom = null): InventoryOrderLine
    {
        $line->loadMissing(['order', 'item']);
        $orderQtySuom = $this->constrainLineQuantityToBudget($line, $orderQtySuom);
        $unitPrice = (float) ($line->unit_price ?? 0);

        if ($orderQtyOuom === null && $line->item) {
            $orderQtyOuom = $this->toOuom($line->item, $orderQtySuom);
        }

        $line->update([
            'order_quantity_suom' => max(0, $orderQtySuom),
            'order_quantity_ouom' => $orderQtyOuom,
            'line_total' => round(max(0, $orderQtySuom) * $unitPrice, 2),
        ]);

        return $line->fresh('item');
    }

    public function setOrderSupplier(InventoryOrder $order, int $supplierId): InventoryOrder
    {
        if (! $order->isDraft()) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier can only be changed on draft RFQs.',
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
        $currentLineTotal = (float) ($line->line_total ?? 0);
        $otherLinesTotal = $order->orderTotal() - $currentLineTotal;
        $availableForLine = max(0, $budgetCap - $otherLinesTotal);
        $maxQty = floor(($availableForLine / $unitPrice) * 10000) / 10000;

        return max(0, min($requestedQtySuom, $maxQty));
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

        if ($order->supplier_id) {
            $item->loadMissing('suppliers');

            if (! $item->suppliers->contains('id', (int) $order->supplier_id)) {
                return false;
            }
        }

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
        if ($budgetMode === InventoryOrder::BUDGET_MODE_DAYS) {
            return null;
        }

        if ($periodOfOrderDays !== null) {
            return (float) $periodOfOrderDays;
        }

        if ($budgetMode === InventoryOrder::BUDGET_MODE_AMOUNT) {
            return null;
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
