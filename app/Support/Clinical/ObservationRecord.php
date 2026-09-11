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
        // SRD v6.1 Phase 7 — the clinical-standing lifecycle layered on top
        // of the capture above. Null on every pre-Phase-7 caller (capture(),
        // the flowsheet list) — this codebase doesn't retrofit every row,
        // only what correct()/markEnteredInError()/cancel() actually return.
        public readonly ?string $status = null,
        public readonly int|string|null $supersedesObservationId = null,
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
        // GET clinical/patients/{id}/observations (confirmed live 2026-08-15)
        // returns the captured number as `value`, not `value_numeric` — that
        // name only appears in the *capture* request body, a different
        // endpoint. This list response also has no base_value_normalized at
        // all, only `value` + `display_unit_id`; the Clinical Module doesn't
        // hand back a base-unit figure here, so base_value_numeric stays
        // null rather than guessing at a conversion the caller can't verify.
        $value = $payload['value'] ?? $payload['value_numeric'] ?? null;

        return new self(
            id: $payload['observation_id'] ?? $payload['id'] ?? 0,
            cde_code: (string) ($payload['cde_code'] ?? ''),
            captured_value_numeric: $value !== null ? (float) $value : null,
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

    /**
     * POST clinical/observations/{id}/correct|entered-in-error|cancel — a
     * much thinner response than the capture/flowsheet shapes above (no
     * base-unit conversion is relevant to a status change).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromStatusChangeApi(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? $payload['observation_id'] ?? 0,
            cde_code: (string) ($payload['cde_code'] ?? ''),
            captured_value_numeric: isset($payload['value_numeric']) ? (float) $payload['value_numeric'] : null,
            base_value_numeric: null,
            captured_at: isset($payload['captured_at']) ? Carbon::parse($payload['captured_at']) : null,
            status: $payload['status'] ?? null,
            supersedesObservationId: $payload['supersedes_observation_id'] ?? null,
        );
    }
}
