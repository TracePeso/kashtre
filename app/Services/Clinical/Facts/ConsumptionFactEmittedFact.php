<?php

namespace App\Services\Clinical\Facts;

/**
 * Clinical -> Inventory, per the Clinical-to-Inventory ICD's
 * POST /api/v1/inventory/consumption/emit. Store and item are already
 * resolved to real ids by ConsumptionEventBroker before this is built —
 * the ICD's own architecture has Clinical resolve the sub-store and
 * forward, not Inventory.
 */
class ConsumptionFactEmittedFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $storeId,
        public readonly int $itemId,
        public readonly float $quantity,
        public readonly int $recordedByUserId,
        public readonly string $source,
        public readonly ?string $notes = null,
    ) {
    }

    public function targetModule(): string
    {
        return 'inventory';
    }

    public function factType(): string
    {
        return 'consumption-emit';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'store_id' => $this->storeId,
            'item_id' => $this->itemId,
            'quantity' => $this->quantity,
            'recorded_by_user_id' => $this->recordedByUserId,
            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }
}
