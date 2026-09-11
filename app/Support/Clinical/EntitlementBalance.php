<?php

namespace App\Support\Clinical;

use App\Models\ClinicalEntitlement;

/**
 * One prepaid-package service line's balance — SRD v6.0 §6.3, from
 * `clinical_entitlements` or GET /api/v1/clinical/patients/{id}/entitlements.
 *
 * `remaining_qty` is a stored/generated column on both sides (allocated_qty
 * minus used_qty) — never computed here, so this can never disagree with
 * what the consumption engine itself used to decide INTERNAL vs EXCESS.
 */
class EntitlementBalance
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $packageId,
        public readonly string $serviceCode,
        public readonly int $allocatedQty,
        public readonly int $usedQty,
        public readonly int $remainingQty,
    ) {
    }

    public static function fromModel(ClinicalEntitlement $entitlement): self
    {
        return new self(
            id: $entitlement->id,
            packageId: (string) $entitlement->package_id,
            serviceCode: (string) $entitlement->service_code,
            allocatedQty: (int) $entitlement->allocated_qty,
            usedQty: (int) $entitlement->used_qty,
            remainingQty: (int) $entitlement->remaining_qty,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? '',
            packageId: (string) ($payload['package_id'] ?? ''),
            serviceCode: (string) ($payload['service_code'] ?? ''),
            allocatedQty: (int) ($payload['allocated_qty'] ?? 0),
            usedQty: (int) ($payload['used_qty'] ?? 0),
            remainingQty: (int) ($payload['remaining_qty'] ?? 0),
        );
    }
}
