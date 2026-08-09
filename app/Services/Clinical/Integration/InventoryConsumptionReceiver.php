<?php

namespace App\Services\Clinical\Integration;

use App\Services\Inventory\InventoryStockAnalyticsService;

/**
 * Local receiver for the 'inventory'.'consumption-emit' fact. Deliberately
 * thin — InventoryStockAnalyticsService::recordConsumption() IS the real
 * stock-depletion primitive already used by Imaging's own
 * RadiologyRecipeEngine, so this just calls it with the store/item ids
 * ConsumptionEventBroker already resolved. No store/item resolution
 * happens here — that's Clinical's job per the ICD ("Clinical resolves
 * the sub-store and forwards").
 */
class InventoryConsumptionReceiver
{
    public function __construct(private readonly InventoryStockAnalyticsService $analytics)
    {
    }

    /**
     * @param array<string, mixed> $payload ConsumptionFactEmittedFact::toPayload()
     * @return array{status: string, daily_consumption_id: int}
     */
    public function handle(array $payload): array
    {
        $consumption = $this->analytics->recordConsumption(
            businessId: $payload['business_id'],
            storeId: $payload['store_id'],
            itemId: $payload['item_id'],
            date: now()->toDateString(),
            quantitySuom: $payload['quantity'],
            source: $payload['source'],
            recordedByUserId: $payload['recorded_by_user_id'],
            notes: $payload['notes'],
            occurredAt: now(),
        );

        return [
            'status' => 'DEPLETED',
            'daily_consumption_id' => $consumption->id,
        ];
    }
}
