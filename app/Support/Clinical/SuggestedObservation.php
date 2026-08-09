<?php

namespace App\Support\Clinical;

/**
 * One AI-proposed observation awaiting a clinician's decision — API
 * Integration Guide §10.7.
 *
 * The guide is emphatic and this type exists to enforce it: *nothing the
 * gateway returns reaches the chart until a named clinician accepts it, item
 * by item*. Accepted items then run the same deterministic checks as anything
 * typed by hand — an AI-extracted implausible value is still refused by the
 * physiological guard.
 *
 * So this is deliberately not an ObservationRecord. It is a proposal, and the
 * type system should not let the two be confused.
 *
 * `id` is opaque: a Clinical suggestion-item id under the API driver, a
 * synthetic index under the local one. Pass it back, never interpret it.
 */
class SuggestedObservation
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $cde_code,
        public readonly mixed $value,
        public readonly ?string $unit = null,
        public readonly ?string $display_label = null,
        /** False when the extractor could not map the term onto a registered CDE. */
        public readonly bool $cde_resolved = true,
        /** False when the unit is not in the registry — the value cannot be normalised. */
        public readonly bool $unit_resolved = true,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $item = $payload['payload'] ?? $payload;

        return new self(
            id: $payload['id'] ?? '',
            cde_code: (string) ($item['cde_code'] ?? ''),
            value: $item['value'] ?? null,
            unit: $item['unit'] ?? $item['unit_label'] ?? null,
            display_label: $item['display_label'] ?? null,
            cde_resolved: (bool) ($item['cde_resolved'] ?? true),
            unit_resolved: (bool) ($item['unit_resolved'] ?? true),
        );
    }

    /**
     * Whether this is safe to offer as a one-click accept. An unresolved CDE
     * or unit means the value cannot be normalised, and charting an
     * unconvertible number is worse than not charting it — the clinician has
     * to pick the mapping first.
     */
    public function isCommittable(): bool
    {
        return $this->cde_resolved && $this->unit_resolved && $this->value !== null;
    }
}
