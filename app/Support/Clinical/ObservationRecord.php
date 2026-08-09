<?php

namespace App\Support\Clinical;

use App\Models\CdeObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A charted observation, from cde_observations or
 * GET /api/v1/clinical/patients/{patientId}/observations.
 *
 * Both sides normalise the captured value to the CDE's base unit on write, so
 * `base_value_numeric` is the one to compare against alert boundaries and
 * `captured_value_numeric` is what the clinician actually typed.
 */
class ObservationRecord
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $cde_code,
        public readonly ?float $captured_value_numeric,
        public readonly ?float $base_value_numeric,
        public readonly ?CarbonInterface $captured_at,
        public readonly bool $is_panic_high = false,
        public readonly bool $is_panic_low = false,
        public readonly ?string $captured_value_text = null,
    ) {
    }

    public static function fromModel(CdeObservation $observation): self
    {
        return new self(
            id: $observation->id,
            cde_code: (string) $observation->cde_code,
            captured_value_numeric: $observation->captured_value_numeric !== null ? (float) $observation->captured_value_numeric : null,
            base_value_numeric: $observation->base_value_numeric !== null ? (float) $observation->base_value_numeric : null,
            captured_at: $observation->captured_at,
            captured_value_text: $observation->captured_value_text,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: $payload['observation_id'] ?? $payload['id'] ?? 0,
            cde_code: (string) ($payload['cde_code'] ?? ''),
            captured_value_numeric: isset($payload['value_numeric']) ? (float) $payload['value_numeric'] : null,
            base_value_numeric: isset($payload['base_value_normalized']) ? (float) $payload['base_value_normalized'] : null,
            captured_at: isset($payload['captured_at']) ? Carbon::parse($payload['captured_at']) : null,
            is_panic_high: (bool) ($payload['is_panic_high'] ?? false),
            is_panic_low: (bool) ($payload['is_panic_low'] ?? false),
            captured_value_text: $payload['value_text'] ?? null,
        );
    }

    public function isPanic(): bool
    {
        return $this->is_panic_high || $this->is_panic_low;
    }
}
