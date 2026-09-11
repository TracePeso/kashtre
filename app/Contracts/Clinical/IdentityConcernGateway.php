<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 2, API_GUIDE_V6.1 §Phase 2 — identity-concern reporting.
 * Report only: this raises a flag for someone else to investigate, it
 * never merges or corrects a patient record itself.
 */
interface IdentityConcernGateway
{
    /**
     * @param  array{concern_type: string, description: string, reported_by_user_id: int}  $payload
     * @return array<string, mixed>
     */
    public function report(ClinicalActor $actor, string $patientId, array $payload): array;

    /** @return array<int, array<string, mixed>> */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    /** Facility-wide queue of open identity concerns awaiting resolution. */
    public function open(ClinicalActor $actor): array;

    /** @return array<string, mixed> */
    public function resolve(ClinicalActor $actor, string $concernId, string $resolution): array;
}
