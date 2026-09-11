<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * SRD v6.1 Phase 2, API_GUIDE_V6.1 §Phase 2 — an operational encounter-
 * workspace record layered on top of Main's own `visit_id` (which Main
 * still owns as the encounter concept itself — this is additive, never a
 * claim to that authority, per the guide's own framing of the SRD's
 * unresolved "who owns encounter lifecycle" gap).
 */
interface EncounterGateway
{
    public const CLASS_OUTPATIENT = 'OUTPATIENT';

    public const CLASS_EMERGENCY = 'EMERGENCY';

    public const CLASS_INPATIENT = 'INPATIENT';

    public const CLASS_DAY_CASE = 'DAY_CASE';

    public const CLASS_VIRTUAL = 'VIRTUAL';

    public const CLASS_HOME_COMMUNITY = 'HOME_COMMUNITY';

    public const CLASS_OBSERVATION = 'OBSERVATION';

    /**
     * @param  array{patient_id: string, visit_id: string, encounter_class: string, service?: ?string, facility_id?: ?string, responsible_clinician_id?: ?int, initial_client_space_id?: ?int}  $payload
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException 422 ENCOUNTER_ALREADY_EXISTS on the API driver
     */
    public function create(ClinicalActor $actor, array $payload): array;

    /** @return array<int, array<string, mixed>> */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    /** @return array<string, mixed> */
    public function show(ClinicalActor $actor, string $encounterId): array;

    /**
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException 422 ILLEGAL_ENCOUNTER_TRANSITION, carrying the permitted next statuses
     */
    public function transition(ClinicalActor $actor, string $encounterId, string $status, ?string $reason = null): array;

    /**
     * @return array{ready: bool, items: array<string, int>}
     */
    public function closureChecks(ClinicalActor $actor, string $encounterId): array;

    /**
     * @return array<string, mixed>
     *
     * @throws \App\Services\Clinical\Api\Exceptions\ClinicalRuleRefusedException 422 ENCOUNTER_CLOSURE_ITEMS_OUTSTANDING or ENCOUNTER_NOT_FINISHED
     */
    public function close(ClinicalActor $actor, string $encounterId, bool $override = false): array;

    /**
     * The original closure event is preserved, never erased — closed_at/
     * closed_by_user_id stay untouched; both events remain in history.
     *
     * @return array<string, mixed>
     */
    public function reopen(ClinicalActor $actor, string $encounterId, string $reason): array;
}
