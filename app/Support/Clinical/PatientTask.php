<?php

namespace App\Support\Clinical;

/**
 * One patient on a clinician's worklist — SRD §3.2 "My Patients".
 *
 * Carries the bed context because the board's whole purpose is to answer
 * "where is this patient and do they need me", and a row without a location
 * sends a nurse walking the ward to find out.
 *
 * `is_admitted` is false for outpatients, who have no bed at all. They are
 * deliberately included rather than dropped — an outpatient the clinician is
 * responsible for is still their patient.
 */
class PatientTask
{
    public function __construct(
        public readonly string $patient_id,
        public readonly ?string $visit_id = null,
        public readonly ?string $bed_code = null,
        public readonly ?string $ward_code = null,
        public readonly ?string $ward_name = null,
        public readonly ?string $room_number = null,
        public readonly ?string $building_wing = null,
        public readonly bool $is_overflow_bed = false,
        public readonly bool $is_admitted = false,
        public readonly int $open_tasks = 0,
        public readonly int $unacknowledged_alerts = 0,
    ) {
    }

    public function needsAttention(): bool
    {
        return $this->unacknowledged_alerts > 0 || $this->open_tasks > 0;
    }

    public function location(): string
    {
        if (! $this->is_admitted) {
            return 'Outpatient';
        }

        return trim(implode(' · ', array_filter([
            $this->ward_name ?: $this->ward_code,
            $this->room_number,
            $this->bed_code,
        ]))) ?: 'Admitted';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            patient_id: (string) ($payload['patient_id'] ?? $payload['global_client_id'] ?? ''),
            visit_id: isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
            bed_code: isset($payload['bed_code']) ? (string) $payload['bed_code'] : null,
            ward_code: isset($payload['ward_code']) ? (string) $payload['ward_code'] : null,
            ward_name: isset($payload['ward_name']) ? (string) $payload['ward_name'] : null,
            room_number: isset($payload['room_number']) ? (string) $payload['room_number'] : null,
            building_wing: isset($payload['building_wing']) ? (string) $payload['building_wing'] : null,
            is_overflow_bed: (bool) ($payload['is_overflow_bed'] ?? false),
            is_admitted: (bool) ($payload['is_admitted'] ?? false),
            open_tasks: (int) ($payload['open_tasks'] ?? 0),
            unacknowledged_alerts: (int) ($payload['unacknowledged_alerts'] ?? 0),
        );
    }
}
