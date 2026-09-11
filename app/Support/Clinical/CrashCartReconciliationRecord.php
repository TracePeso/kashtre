<?php

namespace App\Support\Clinical;

/**
 * SRD v6.0 §13.2 — the single-submission emergency reconciliation: what was
 * taken from a crash cart during a resuscitation, charted, decremented from
 * stock, and billed, all from one form after the crisis has passed.
 */
class CrashCartReconciliationRecord
{
    /**
     * @param  array<int, array{inventory_sku: string, quantity_used: float}>  $items
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $resuscitationEventId,
        public readonly string $patientId,
        public readonly ?string $visitId,
        public readonly string $crashCartStoreId,
        public readonly ?string $narrative,
        public readonly array $items,
        public readonly ?string $reconciledAt = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? '',
            resuscitationEventId: (string) ($payload['resuscitation_event_id'] ?? ''),
            patientId: (string) ($payload['patient_id'] ?? ''),
            visitId: $payload['visit_id'] ?? null,
            crashCartStoreId: (string) ($payload['crash_cart_store_id'] ?? ''),
            narrative: $payload['narrative'] ?? null,
            items: array_map(
                fn (array $item) => [
                    'inventory_sku' => (string) $item['inventory_sku'],
                    'quantity_used' => (float) $item['quantity_used'],
                ],
                $payload['items'] ?? [],
            ),
            reconciledAt: $payload['reconciled_at'] ?? null,
        );
    }
}
