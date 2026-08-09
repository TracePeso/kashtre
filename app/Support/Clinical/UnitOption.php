<?php

namespace App\Support\Clinical;

use App\Models\ClinicalUomMaster;

/**
 * A selectable unit of measure, from clinical_uom_master or
 * GET /api/v1/settings/dictionaries/units-of-measure.
 *
 * Units are tenant-configurable on both sides — API Integration Guide §10.9 is
 * explicit that no module should hardcode a clinical value.
 */
class UnitOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $unit_label,
    ) {
    }

    public static function fromModel(ClinicalUomMaster $unit): self
    {
        return new self((int) $unit->id, (string) $unit->unit_label);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: (int) $payload['id'],
            unit_label: (string) ($payload['unit_label'] ?? $payload['label'] ?? ''),
        );
    }
}
