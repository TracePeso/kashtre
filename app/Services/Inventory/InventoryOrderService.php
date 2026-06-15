<?php

namespace App\Services\Inventory;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryModuleConfig;
use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryOrderService
{
    /** Consumption rate window — auto-applied (15-day MA per Excel V/AA). */
    public const AUTO_CONSUMPTION_RATE_DAYS = 15;

    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics
    ) {}

    public function generateOrderNumber(int $businessId): string
    {
        $prefix = 'ORD-'.now()->format('Ymd');
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
        ?float $safetyStockDays = null,
        ?float $bufferStockDays = null,
        ?float $notificationToOrderDays = null
    ): InventoryOrder {
        return DB::transaction(function () use ($businessId, $storeId, $user, $importanceFilter, $budgetMode, $budgetValue, $periodOfOrderDays, $notes, $groupId, $subgroupId, $peakPeriodPercent, $safetyStockDays, $bufferStockDays, $notificationToOrderDays) {
            $config = InventoryModuleConfig::query()
                ->forBusiness($businessId)
                ->active()
                ->first();

            $order = InventoryOrder::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'order_number' => $this->generateOrderNumber($businessId),
                'status' => InventoryOrder::STATUS_DRAFT,
                'importance_filter' => $importanceFilter,
                'group_id' => $groupId,
                'subgroup_id' => $subgroupId,
                'budget_mode' => $budgetMode,
                'budget_value' => $budgetValue,
                'moving_average_days' => self::AUTO_CONSUMPTION_RATE_DAYS,
                'period_of_order_days' => $periodOfOrderDays ?? (float) ($config?->period_of_order_days ?? 30),
                'safety_stock_days' => $safetyStockDays ?? (float) ($config?->safety_stock_days ?? 0),
                'buffer_stock_days' => $bufferStockDays ?? (float) ($config?->buffer_stock_days ?? 0),
                'notification_to_order_days' => $notificationToOrderDays ?? (float) ($config?->notification_to_order_days ?? 0),
                'peak_period_percent' => max(0, (float) ($peakPeriodPercent ?? 0)),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            $this->populateLines($order);

            return $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'lines.item.suppliers', 'lines.supplier', 'store']);
        });
    }

    public function populateLines(InventoryOrder $order): void
    {
        $config = InventoryModuleConfig::query()
            ->forBusiness((int) $order->business_id)
            ->active()
            ->first();

        $stockLevels = InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->where(function ($query) {
                $query->where('quantity_suom', '>', 0)
                    ->orWhere('ma_15_days', '>', 0)
                    ->orWhere('ma_30_days', '>', 0);
            })
            ->with(['item.itemUnit', 'item.orderUnit', 'item.suppliers'])
            ->get();

        $order->lines()->delete();

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_DAYS && $order->budget_value > 0) {
            $this->populateBudgetDaysLines($order, $stockLevels, $config);

            return;
        }

        $periodDays = (float) ($order->period_of_order_days ?? $config?->period_of_order_days ?? 30);

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            $dailyAvg = $this->analytics->excelDailyUsageSuom($stock, $config);
            $baseSuggested = $this->analytics->suggestedOrderQtyPeriod($stock, $config, $periodDays, $order);
            $arStock = $this->analytics->systemStockArSuom($stock, $config);
            $unitPrice = $this->analytics->purchasePricePerSuom($stock, $item);
            $supplierId = $item->suppliers->first()?->id;

            $this->createOrderLine(
                $order,
                $item,
                $baseSuggested,
                $dailyAvg,
                $arStock,
                $unitPrice,
                $supplierId,
                0
            );
        }

        $this->applyBudgetConstraints($order->fresh(['lines']), $config);
    }

    /**
     * Excel budget path AH–AL: proportional order days from a target stock-days budget.
     *
     * @param  Collection<int, InventoryStockLevel>  $stockLevels
     */
    private function populateBudgetDaysLines(
        InventoryOrder $order,
        Collection $stockLevels,
        ?InventoryModuleConfig $config
    ): void {
        $budgetDays = (float) $order->budget_value;
        $rows = [];

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $this->itemPassesOrderFilters($item, $order)) {
                continue;
            }

            $rows[] = [
                'stock' => $stock,
                'item' => $item,
                'days_left' => $this->analytics->daysLeftToOrder($stock, $config, $order) ?? 0,
                'daily_avg' => $this->analytics->excelDailyUsageSuom($stock, $config),
                'test_amount' => $this->analytics->budgetTestAmountUgx($stock, $config, $item),
                'unit_price' => $this->analytics->purchasePricePerSuom($stock, $item),
                'ar_stock' => $this->analytics->systemStockArSuom($stock, $config),
            ];
        }

        if ($rows === []) {
            return;
        }

        $avgDaysLeft = collect($rows)->avg('days_left');
        $sumTestAmount = collect($rows)->sum('test_amount');

        foreach ($rows as $row) {
            $gap = $row['days_left'] - $avgDaysLeft;
            $orderDays = $sumTestAmount > 0
                ? max(0, (15 * $budgetDays / $sumTestAmount) - $gap)
                : 0;
            $baseSuggested = max(0, round($orderDays * $row['daily_avg'], 4));
            $supplierId = $row['item']->suppliers->first()?->id;

            $this->createOrderLine(
                $order,
                $row['item'],
                $baseSuggested,
                $row['daily_avg'],
                $row['ar_stock'],
                $row['unit_price'],
                $supplierId,
                0
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
        float $unitPrice,
        ?int $supplierId,
        float $consumptionIncreasePercent
    ): void {
        $peakImpact = self::computePeakImpactPercent($order->peak_period_percent, $consumptionIncreasePercent);
        $suggested = $this->applyPeakToSuggestedQuantity($baseSuggested, $peakImpact);

        InventoryOrderLine::create([
            'inventory_order_id' => $order->id,
            'item_id' => $item->id,
            'supplier_id' => $supplierId,
            'daily_average_suom' => $dailyAvg,
            'lead_time_days' => $this->averageLeadTimeDays((int) $order->business_id, (int) $item->id),
            'system_quantity_suom' => $arStock,
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
            return 'No items have stock or consumption history at this store. Receive goods via a GRN or wait for sale consumption, then refresh lines.';
        }

        if ($order->importance_filter) {
            $label = Item::importanceOptions()[$order->importance_filter] ?? $order->importance_filter;

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
                    return "This order filters to {$label} items only, but {$uncategorizedStock} stocked item(s) have no importance category. Create a new order with \"All items\", or set categories on your goods under Items.";
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

        $order->load('lines');

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_AMOUNT) {
            $total = $order->orderTotal();

            if ($total <= (float) $order->budget_value) {
                return;
            }

            $factor = (float) $order->budget_value / $total;

            foreach ($order->lines as $line) {
                $qty = round((float) $line->order_quantity_suom * $factor, 4);
                $this->updateLine($line, $qty);
            }
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
        $unitPrice = (float) ($line->unit_price ?? 0);

        $line->update([
            'order_quantity_suom' => max(0, $orderQtySuom),
            'order_quantity_ouom' => $orderQtyOuom,
            'line_total' => round(max(0, $orderQtySuom) * $unitPrice, 2),
        ]);

        return $line->fresh('item');
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

        if ($order->importance_filter && $item->importance_category !== $order->importance_filter) {
            return false;
        }

        if ($order->group_id && (int) $item->group_id !== (int) $order->group_id) {
            return false;
        }

        if ($order->subgroup_id && (int) $item->subgroup_id !== (int) $order->subgroup_id) {
            return false;
        }

        return true;
    }
}
