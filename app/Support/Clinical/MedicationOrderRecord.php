<?php

namespace App\Support\Clinical;

use App\Models\ClinicalMedicationOrder;

/**
 * An active prescription, from clinical_medication_orders or
 * POST/GET /api/v1/clinical/orders/medications.
 *
 * `id` is int locally and a `MEDORD-…` uuid over the API — hence the union
 * type. Never do arithmetic on it; pass it straight back as an opaque handle.
 *
 * `is_external` marks the API's `fulfilment: "EXTERNAL"` case: nothing in the
 * catalogue matched, so an external referral was generated instead of blocking
 * the clinician (§10.3).
 */
class MedicationOrderRecord
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $drug_display_name,
        public readonly ?string $drug_code = null,
        public readonly ?float $dose_amount = null,
        public readonly ?string $route_code = null,
        public readonly ?string $frequency_code = null,
        public readonly bool $is_external = false,
        public readonly ?string $cdss_override_reason = null,
        public readonly string $status = 'ACTIVE',
        public readonly ?string $external_referral_path = null,
    ) {
    }

    public static function fromModel(ClinicalMedicationOrder $order): self
    {
        return new self(
            id: $order->id,
            drug_display_name: (string) $order->drug_display_name,
            drug_code: $order->drug_code,
            dose_amount: $order->dose_amount !== null ? (float) $order->dose_amount : null,
            route_code: $order->route_code,
            frequency_code: $order->frequency_code,
            is_external: (bool) $order->is_external,
            cdss_override_reason: $order->cdss_override_reason,
            status: (string) $order->status,
            external_referral_path: $order->external_referral_path,
        );
    }

    /**
     * The API returns an order with an `items` array; a single-item order maps
     * onto this flat record, which is what the current UI prescribes.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $item = $payload['items'][0] ?? [];

        return new self(
            id: $payload['order_uuid'] ?? $payload['id'] ?? '',
            drug_display_name: (string) ($item['display_name'] ?? $item['requested_term'] ?? $payload['display_label'] ?? ''),
            drug_code: $item['inventory_sku'] ?? $item['item_code'] ?? null,
            dose_amount: isset($item['dose_quantity']) ? (float) $item['dose_quantity'] : null,
            route_code: $item['route_code'] ?? null,
            frequency_code: $item['frequency_code'] ?? null,
            is_external: ($payload['fulfilment'] ?? 'INTERNAL') === 'EXTERNAL',
            cdss_override_reason: $payload['cdss_override_reason_code'] ?? null,
            status: (string) ($payload['status'] ?? 'ACTIVE'),
            external_referral_path: $payload['external_referral_url'] ?? null,
        );
    }
}
