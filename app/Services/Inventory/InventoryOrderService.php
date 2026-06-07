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
use Illuminate\Support\Facades\DB;

class InventoryOrderService
{
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
        int $movingAverageDays = 30,
        ?float $periodOfOrderDays = null,
        ?string $notes = null
    ): InventoryOrder {
        return DB::transaction(function () use ($businessId, $storeId, $user, $importanceFilter, $budgetMode, $budgetValue, $movingAverageDays, $periodOfOrderDays, $notes) {
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
                'budget_mode' => $budgetMode,
                'budget_value' => $budgetValue,
                'moving_average_days' => $movingAverageDays,
                'period_of_order_days' => $periodOfOrderDays ?? (float) ($config?->period_of_order_days ?? 30),
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            $this->populateLines($order);

            return $order->fresh(['lines.item.itemUnit', 'lines.item.orderUnit', 'store']);
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
            ->where('quantity_suom', '>', 0)
            ->with(['item.itemUnit', 'item.orderUnit'])
            ->get();

        $maDays = (int) ($order->moving_average_days ?: 30);
        $periodDays = (float) ($order->period_of_order_days ?? $config?->period_of_order_days ?? 30);
        $notificationDays = (float) ($config?->notification_to_order_days ?? 0);

        $order->lines()->delete();

        foreach ($stockLevels as $stock) {
            $item = $stock->item;

            if (! $item || $item->type !== 'good') {
                continue;
            }

            if ($order->importance_filter && $item->importance_category !== $order->importance_filter) {
                continue;
            }

            $dailyAvg = $this->analytics->movingAverageForStock($stock, $maDays);

            if ($dailyAvg <= 0) {
                $dailyAvg = $this->analytics->effectiveDailyUsage($stock, $config);
            }

            $safetyDays = $this->analytics->safetyStockDays($stock, $config);
            $bufferDays = $this->analytics->bufferStockDays($stock, $config);
            $leadTimeDays = $this->averageLeadTimeDays((int) $order->business_id, (int) $item->id);
            $coverageDays = $safetyDays + $bufferDays + $leadTimeDays + $notificationDays + $periodDays;
            $systemQty = (float) $stock->quantity_suom;
            $targetQty = $dailyAvg * $coverageDays;
            $suggested = max(0, round($targetQty - $systemQty, 4));
            $unitPrice = (float) ($stock->last_purchase_price ?? $item->default_price ?? 0);
            $orderQtySuom = $suggested;
            $orderQtyOuom = null;

            if ($item->suom_per_ouom && (float) $item->suom_per_ouom > 0) {
                $orderQtyOuom = round($orderQtySuom / (float) $item->suom_per_ouom, 4);
            }

            InventoryOrderLine::create([
                'inventory_order_id' => $order->id,
                'item_id' => $item->id,
                'daily_average_suom' => $dailyAvg,
                'lead_time_days' => $leadTimeDays,
                'system_quantity_suom' => $systemQty,
                'suggested_quantity_suom' => $suggested,
                'order_quantity_suom' => $orderQtySuom,
                'order_quantity_ouom' => $orderQtyOuom,
                'unit_price' => $unitPrice,
                'line_total' => round($orderQtySuom * $unitPrice, 2),
            ]);
        }

        $this->applyBudgetConstraints($order->fresh(['lines']), $config);
    }

    public function explainEmptyOrder(InventoryOrder $order): string
    {
        $stockCount = InventoryStockLevel::query()
            ->where('business_id', $order->business_id)
            ->where('store_id', $order->store_id)
            ->where('quantity_suom', '>', 0)
            ->count();

        if ($stockCount === 0) {
            return 'No items have system stock at this store. Receive goods via a GRN first, then refresh lines.';
        }

        if ($order->importance_filter) {
            $label = Item::importanceOptions()[$order->importance_filter] ?? $order->importance_filter;

            $matchingStock = InventoryStockLevel::query()
                ->where('business_id', $order->business_id)
                ->where('store_id', $order->store_id)
                ->where('quantity_suom', '>', 0)
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

            return;
        }

        if ($order->budget_mode === InventoryOrder::BUDGET_MODE_DAYS) {
            $targetDays = (float) $order->budget_value;

            foreach ($order->lines as $line) {
                $line->loadMissing('item');
                $dailyAvg = (float) $line->daily_average_suom;

                if ($dailyAvg <= 0) {
                    continue;
                }

                $targetQty = $dailyAvg * $targetDays;
                $suggested = max(0, round($targetQty - (float) $line->system_quantity_suom, 4));
                $ouom = null;
                $item = $line->item;

                if ($item && $item->suom_per_ouom && (float) $item->suom_per_ouom > 0) {
                    $ouom = round($suggested / (float) $item->suom_per_ouom, 4);
                }

                $this->updateLine($line, $suggested, $ouom);
                $line->update(['suggested_quantity_suom' => $suggested]);
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

    public function submit(InventoryOrder $order): InventoryOrder
    {
        $order->update([
            'status' => InventoryOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $order->fresh(['lines.item', 'store']);
    }
}
