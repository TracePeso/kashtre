<?php

namespace App\Services\Inventory;

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
        ?string $notes = null
    ): InventoryOrder {
        return DB::transaction(function () use ($businessId, $storeId, $user, $importanceFilter, $budgetMode, $budgetValue, $movingAverageDays, $notes) {
            $order = InventoryOrder::create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'order_number' => $this->generateOrderNumber($businessId),
                'status' => InventoryOrder::STATUS_DRAFT,
                'importance_filter' => $importanceFilter,
                'budget_mode' => $budgetMode,
                'budget_value' => $budgetValue,
                'moving_average_days' => $movingAverageDays,
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

        $itemsQuery = Item::query()
            ->where('business_id', $order->business_id)
            ->where('type', 'good')
            ->with(['itemUnit', 'orderUnit']);

        if ($order->importance_filter) {
            $itemsQuery->where('importance_category', $order->importance_filter);
        }

        $items = $itemsQuery->orderBy('name')->get();
        $maDays = (int) ($order->moving_average_days ?: 30);

        $order->lines()->delete();

        foreach ($items as $item) {
            $stock = InventoryStockLevel::query()
                ->where('business_id', $order->business_id)
                ->where('store_id', $order->store_id)
                ->where('item_id', $item->id)
                ->first();

            if (! $stock) {
                continue;
            }

            $dailyAvg = $this->analytics->movingAverageForStock($stock, $maDays);

            if ($dailyAvg <= 0) {
                $dailyAvg = $this->analytics->effectiveDailyUsage($stock, $config);
            }

            $safetyDays = $this->analytics->safetyStockDays($stock, $config);
            $bufferDays = $this->analytics->bufferStockDays($stock, $config);
            $leadTimeDays = 0;
            $systemQty = (float) $stock->quantity_suom;
            $targetQty = ($dailyAvg * ($safetyDays + $bufferDays + $leadTimeDays));
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
