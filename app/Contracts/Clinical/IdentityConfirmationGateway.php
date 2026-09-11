<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 2 (Patient/Encounter Context), API_GUIDE_V6.1 §Phase 2 —
 * positive patient identification, generalizing MAR's existing 5-Rights
 * check to 7 more action types beyond medication administration.
 */
interface IdentityConfirmationGateway
{
    /**
     * @param  array{action_type: string, confirmed_by_user_id: int, method?: ?string, notes?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function confirm(ClinicalActor $actor, string $patientId, array $payload): array;

    /** @return array<int, array<string, mixed>> */
    public function forPatient(ClinicalActor $actor, string $patientId): array;
}
