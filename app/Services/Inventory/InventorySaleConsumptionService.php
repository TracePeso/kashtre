<?php

namespace App\Services\Inventory;

use App\Models\InventoryModuleConfig;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\ServiceDeliveryQueue;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class InventorySaleConsumptionService
{
    public function __construct(
        private readonly InventoryStockAnalyticsService $analytics
    ) {}

    public function recordFromQueue(ServiceDeliveryQueue $queue, ?int $userId = null): void
    {
        $queue->loadMissing(['item', 'business']);

        $item = $queue->item;

        if (! $item || $item->type !== 'good') {
            return;
        }

        $businessId = (int) $queue->business_id;

        if (! InventoryModuleConfig::query()->forBusiness($businessId)->active()->exists()) {
            return;
        }

        $storeId = $this->resolveStoreId($queue, $userId);

        if (! $storeId) {
            Log::info('Inventory auto-consumption skipped: no store resolved', [
                'queue_id' => $queue->id,
                'item_id' => $item->id,
            ]);

            return;
        }

        $quantity = (float) ($queue->quantity ?? 1);

        if ($quantity <= 0) {
            return;
        }

        $existing = InventoryStockLevel::query()
            ->where('business_id', $businessId)
            ->where('store_id', $storeId)
            ->where('item_id', $item->id)
            ->value('quantity_suom');

        if ($existing === null || (float) $existing <= 0) {
            return;
        }

        $this->analytics->recordConsumption(
            $businessId,
            $storeId,
            (int) $item->id,
            now()->toDateString(),
            $quantity,
            \App\Models\InventoryDailyConsumption::SOURCE_SALE,
            $userId,
            'Auto from sale: '.($queue->item_name ?? $item->name)
        );
    }

    private function resolveStoreId(ServiceDeliveryQueue $queue, ?int $userId): ?int
    {
        if ($userId) {
            $userStore = User::query()->whereKey($userId)->value('default_store_id');

            if ($userStore) {
                return (int) $userStore;
            }
        }

        $stockStore = InventoryStockLevel::query()
            ->where('business_id', $queue->business_id)
            ->where('item_id', $queue->item_id)
            ->where('quantity_suom', '>', 0)
            ->orderByDesc('quantity_suom')
            ->value('store_id');

        return $stockStore ? (int) $stockStore : null;
    }
}
