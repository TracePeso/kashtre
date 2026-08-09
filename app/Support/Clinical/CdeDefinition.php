<?php

namespace App\Support\Clinical;

use App\Models\CdeRegistry;

/**
 * One Clinical Data Element definition, from either the local cde_registry
 * table or GET /api/v1/settings/cde-registry.
 *
 * Property names deliberately mirror the local model's columns so the Blade
 * views render unchanged whichever side of the strangler is active.
 */
class CdeDefinition
{
    public function __construct(
        public readonly string $cde_code,
        public readonly string $cde_name,
        public readonly ?int $base_uom_id = null,
        public readonly string $data_type = 'NUMERIC',
        public readonly ?float $physiological_min = null,
        public readonly ?float $physiological_max = null,
        public readonly ?float $critical_low = null,
        public readonly ?float $critical_high = null,
    ) {
    }

    public static function fromModel(CdeRegistry $cde): self
    {
        return new self(
            cde_code: $cde->cde_code,
            cde_name: (string) $cde->cde_name,
            base_uom_id: $cde->base_uom_id ? (int) $cde->base_uom_id : null,
            data_type: (string) $cde->data_type,
            physiological_min: $cde->physiological_min !== null ? (float) $cde->physiological_min : null,
            physiological_max: $cde->physiological_max !== null ? (float) $cde->physiological_max : null,
            critical_low: $cde->critical_low !== null ? (float) $cde->critical_low : null,
            critical_high: $cde->critical_high !== null ? (float) $cde->critical_high : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            cde_code: (string) $payload['cde_code'],
            cde_name: (string) ($payload['cde_name'] ?? $payload['display_label'] ?? $payload['cde_code']),
            base_uom_id: isset($payload['base_uom_id']) ? (int) $payload['base_uom_id'] : null,
            data_type: (string) ($payload['data_type'] ?? 'NUMERIC'),
            physiological_min: isset($payload['physiological_min']) ? (float) $payload['physiological_min'] : null,
            physiological_max: isset($payload['physiological_max']) ? (float) $payload['physiological_max'] : null,
            critical_low: isset($payload['critical_low']) ? (float) $payload['critical_low'] : null,
            critical_high: isset($payload['critical_high']) ? (float) $payload['critical_high'] : null,
        );
    }
}
