<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Adverse drug reaction / medication error / near-miss reporting — SRD v6.1
 * Phase 6, API_GUIDE_V6.1 §Phase 6. Separate from the reconciliation flow
 * above: this reports something that already happened to a patient, not a
 * decision about what they're taking.
 */
interface MedicationAdverseEventGateway
{
    public const TYPE_ADVERSE_DRUG_REACTION = 'ADVERSE_DRUG_REACTION';

    public const TYPE_SIDE_EFFECT = 'SIDE_EFFECT';

    public const TYPE_MEDICATION_ERROR = 'MEDICATION_ERROR';

    public const TYPE_NEAR_MISS = 'NEAR_MISS';

    public const TYPE_THERAPEUTIC_FAILURE = 'THERAPEUTIC_FAILURE';

    /**
     * @param  array{event_type: string, description: string, severity: string, reported_by_user_id: int}  $payload
     * @return array<string, mixed>
     */
    public function report(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array;

    /** @return array<int, array<string, mixed>> */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    /** @return array<string, mixed> */
    public function recordResponse(ClinicalActor $actor, string $eventId, string $clinicalResponse, ?string $outcome = null): array;

    /** Sets status to UNDER_REVIEW, escalated: true. */
    public function escalate(ClinicalActor $actor, string $eventId): array;

    /** @return array<string, mixed> */
    public function close(ClinicalActor $actor, string $eventId, string $outcome): array;
}
