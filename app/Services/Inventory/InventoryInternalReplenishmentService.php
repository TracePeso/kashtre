<?php

namespace App\Services\Inventory;

use App\Models\InventoryOrder;
use App\Models\InventoryOrderLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryUsageEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryInternalReplenishmentService
{
    public function __construct(
        private readonly InventoryDaysOfStockService $daysOfStock,
        private readonly InventoryOrderService $orders,
    ) {}

    /**
     * Draft an internal child→parent replenishment order (SRD §7.5).
     *
     * @param  array{
     *     child_store_id: int,
     *     forecast_basis?: string,
     *     edit_mode?: string,
     *     coverage_days?: float|null,
     *     item_ids?: list<int>|null,
     *     priority?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function draft(array $data, User $user): InventoryOrder
    {
        $child = Store::query()->findOrFail((int) $data['child_store_id']);
        $parent = $child->parent;

        if (! $parent) {
            throw ValidationException::withMessages([
                'child_store_id' => 'Child store has no parent supply node.',
            ]);
        }

        $basis = $data['forecast_basis'] ?? InventoryDaysOfStockService::FORECAST_CONSUMPTION;
        $coverage = (float) ($data['coverage_days'] ?? $child->max_stock_days ?? 30);
        if ($coverage <= 0) {
            $coverage = 30;
        }

        $itemIds = $data['item_ids'] ?? null;
        $levels = InventoryStockLevel::query()
            ->where('business_id', $child->business_id)
            ->where('store_id', $child->id)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->when($itemIds, fn ($q) => $q->whereIn('item_id', $itemIds))
            ->get();

        return DB::transaction(function () use ($child, $parent, $basis, $coverage, $levels, $user, $data) {
            $order = InventoryOrder::query()->create([
                'business_id' => $child->business_id,
                'store_id' => $child->id,
                'order_type' => InventoryOrder::TYPE_INTERNAL,
                'source_store_id' => $parent->id,
                'order_number' => 'INT-'.now()->format('YmdHis').'-'.$child->id,
                'status' => InventoryOrder::STATUS_DRAFT,
                'budget_mode' => InventoryOrder::BUDGET_MODE_DAYS,
                'forecast_basis' => $basis === InventoryDaysOfStockService::FORECAST_DEMAND
                    ? InventoryOrder::FORECAST_DEMAND
                    : InventoryOrder::FORECAST_CONSUMPTION,
                'budget_value' => $coverage,
                'moving_average_days' => $this->daysOfStock->forecastWindowDays($coverage),
                'period_of_order_days' => $coverage,
                'notes' => $data['notes'] ?? ('Internal replenishment ('.$basis.')'),
                'created_by_user_id' => $user->id,
                'item_ids' => $levels->pluck('item_id')->values()->all(),
            ]);

            foreach ($levels as $level) {
                $suggested = $this->daysOfStock->suggestedUnitsToMax($child, (int) $level->item_id, $basis);
                if ($suggested <= 0) {
                    continue;
                }

                InventoryOrderLine::query()->create([
                    'inventory_order_id' => $order->id,
                    'item_id' => $level->item_id,
                    'order_quantity_suom' => $suggested,
                    'suggested_quantity_suom' => $suggested,
                    'base_suggested_quantity_suom' => $suggested,
                    'system_quantity_suom' => $level->quantity_suom,
                    'current_stock_suom' => $level->quantity_suom,
                    'order_days' => $coverage,
                ]);
            }

            return $order->fresh(['lines']);
        });
    }

    public function seedFromCrashCartUsage(InventoryUsageEvent $event, User $user): ?InventoryOrder
    {
        if ($event->context !== InventoryUsageEvent::CONTEXT_CRASH_CART || ! $event->store_id || ! $event->item_id) {
            return null;
        }

        return $this->draft([
            'child_store_id' => (int) $event->store_id,
            'item_ids' => [(int) $event->item_id],
            'forecast_basis' => InventoryDaysOfStockService::FORECAST_CONSUMPTION,
            'priority' => 'high',
            'notes' => 'High-priority crash cart replenishment for usage '.$event->uuid,
        ], $user);
    }
}
