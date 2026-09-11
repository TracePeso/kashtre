<?php

namespace App\Services\Clinical\Facts;

/**
 * LIMS -> Clinical, per the Clinical-to-LIMS ICD's "lab-reagent-proxy"
 * (REAGENT_CONSUMPTION_FACT) event. Unlike Imaging (a real, already-built
 * module that depletes its own stock directly via RadiologyRecipeEngine),
 * LIMS has no real code of its own — this is Clinical's only path to
 * record lab reagent consumption, so it genuinely proxies through
 * ConsumptionEventBroker (Chunk 4) as the ICD's architecture describes.
 */
class LabReagentConsumptionFact extends Fact
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $clientId,
        public readonly ?string $visitId,
        public readonly int $scientistUserId,
        public readonly string $itemCode,
        public readonly float $quantity,
    ) {
    }

    public function targetModule(): string
    {
        return 'clinical';
    }

    public function factType(): string
    {
        return 'lab-reagent-consumption';
    }

    public function toPayload(): array
    {
        return [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'client_id' => $this->clientId,
            'visit_id' => $this->visitId,
            'scientist_user_id' => $this->scientistUserId,
            'item_code' => $this->itemCode,
            'quantity' => $this->quantity,
        ];
    }
}
