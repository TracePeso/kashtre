<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryDemandLedger;
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
     * Draft an internal child→parent replenishment order.
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
                'child_store_id' => 'This store has no parent store to request stock from.',
            ]);
        }

        $basis = $data['forecast_basis'] ?? InventoryDaysOfStockService::FORECAST_CONSUMPTION;
        $coverage = (float) ($data['coverage_days'] ?? $child->max_stock_days ?? 30);
        if ($coverage <= 0) {
            $coverage = 30;
        }

        $explicitItemIds = isset($data['item_ids']) && is_array($data['item_ids'])
            ? array_values(array_unique(array_map('intval', $data['item_ids'])))
            : null;

        $candidateItemIds = $explicitItemIds ?? $this->candidateItemIds($child, $basis);

        if ($candidateItemIds === []) {
            throw ValidationException::withMessages([
                'child_store_id' => 'No stock or usage history found for “'.$child->name.'”. Choose another store or record usage first.',
            ]);
        }

        $levelsByItem = InventoryStockLevel::query()
            ->where('business_id', $child->business_id)
            ->where('store_id', $child->id)
            ->whereIn('item_id', $candidateItemIds)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->get()
            ->keyBy('item_id');

        return DB::transaction(function () use ($child, $parent, $basis, $coverage, $candidateItemIds, $levelsByItem, $user, $data) {
            $linePayloads = [];

            $skippedAboveReorder = 0;
            $skippedNoSuggestion = 0;

            foreach ($candidateItemIds as $itemId) {
                $level = $levelsByItem->get($itemId);
                $onHand = round((float) ($level?->quantity_suom ?? 0), 4);

                $reorderDays = (float) ($child->reorder_level_days ?? 0);
                if ($reorderDays > 0) {
                    $stockDays = $this->daysOfStock->currentStockDays(
                        (int) $child->business_id,
                        (int) $child->id,
                        $itemId
                    );
                    // Only draft lines that are at/below the store reorder horizon.
                    // null stock days (no MA yet) is treated as needing replenishment.
                    if ($stockDays !== null && $stockDays > $reorderDays) {
                        $skippedAboveReorder++;

                        continue;
                    }
                }

                $suggested = $this->daysOfStock->suggestedUnitsToMax(
                    $child,
                    $itemId,
                    $basis,
                    $coverage
                );

                if ($suggested <= 0) {
                    $skippedNoSuggestion++;

                    continue;
                }

                $linePayloads[] = [
                    'item_id' => $itemId,
                    'order_quantity_suom' => $suggested,
                    'suggested_quantity_suom' => $suggested,
                    'base_suggested_quantity_suom' => $suggested,
                    'system_quantity_suom' => $onHand,
                    'current_stock_suom' => $onHand,
                    'order_days' => $coverage,
                ];
            }

            if ($linePayloads === []) {
                if ($skippedAboveReorder > 0 && (float) ($child->reorder_level_days ?? 0) > 0) {
                    $message = 'Stock at “'.$child->name.'” is still above the reorder level. No items need replenishing right now.';
                } elseif ($skippedNoSuggestion > 0) {
                    $message = 'No items need replenishing for “'.$child->name.'” at the selected coverage.';
                } else {
                    $message = 'Unable to build a replenishment draft for “'.$child->name.'”. Check store settings and try again.';
                }

                throw ValidationException::withMessages([
                    'child_store_id' => $message,
                ]);
            }

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
                'item_ids' => collect($linePayloads)->pluck('item_id')->values()->all(),
            ]);

            foreach ($linePayloads as $payload) {
                InventoryOrderLine::query()->create([
                    'inventory_order_id' => $order->id,
                    ...$payload,
                ]);
            }

            return $order->fresh(['lines']);
        });
    }

    /**
     * @return list<int>
     */
    protected function candidateItemIds(Store $child, string $basis): array
    {
        $fromStock = InventoryStockLevel::query()
            ->where('business_id', $child->business_id)
            ->where('store_id', $child->id)
            ->where(function ($q) {
                $q->whereNull('stock_zone')->orWhere('stock_zone', 'active');
            })
            ->pluck('item_id');

        $windowStart = now()->subDays(365)->startOfDay();

        if ($basis === InventoryDaysOfStockService::FORECAST_DEMAND) {
            $fromHistory = InventoryDemandLedger::query()
                ->where('business_id', $child->business_id)
                ->where('store_id', $child->id)
                ->where('occurred_at', '>=', $windowStart)
                ->distinct()
                ->pluck('item_id');
        } else {
            $fromHistory = InventoryDailyConsumption::query()
                ->where('business_id', $child->business_id)
                ->where('store_id', $child->id)
                ->where('consumption_date', '>=', $windowStart->toDateString())
                ->where('source', '!=', InventoryDailyConsumption::SOURCE_WASTAGE_EXPIRED)
                ->distinct()
                ->pluck('item_id');
        }

        return $fromStock->merge($fromHistory)->unique()->map(fn ($id) => (int) $id)->values()->all();
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
