<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 2, API_GUIDE_V6.1 §Phase 2 — the general-purpose "open a
 * patient's chart" projection: banner, longitudinal timeline, patient
 * lists. Distinct from the existing `GET /clinical/handover` and
 * ward-census endpoints — those are a shift/ward view, this is a single
 * patient's own workspace.
 */
interface PatientWorkspaceGateway
{
    public const LIST_MY_PATIENTS = 'my_patients';

    public const LIST_MY_TEAM = 'my_team';

    public const LIST_COVERING_PATIENTS = 'covering_patients';

    public const LIST_RECENT_PATIENTS = 'recent_patients';

    /**
     * "confidentiality.restricted" is deliberately a bare boolean — the
     * actual sensitivity label/reason is never included here regardless of
     * caller authorization (CLN-P2-BNR-004).
     *
     * @return array<string, mixed>
     */
    public function banner(ClinicalActor $actor, string $patientId, ?string $visitId = null, ?int $viewingUserId = null): array;

    /**
     * Merges notes/observations/orders/problems, ordered by each entry's
     * own clinically meaningful time, not created_at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(ClinicalActor $actor, string $patientId, ?string $visitId = null, int $limit = 100): array;

    /**
     * @param  array<int, string>  $roleCodes
     * @return array<int, array<string, mixed>>
     */
    public function patientList(ClinicalActor $actor, string $type, int $userId, array $roleCodes = []): array;
}
