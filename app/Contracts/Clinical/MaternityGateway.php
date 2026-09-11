<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * The birth event — API Integration Guide §10.8 / SRD §13.1. Written to
 * the mother's chart, so it sits behind the same care-relationship gate as
 * any other chart write.
 *
 * Known issue, not ours: `recordApgar()` 403s with REBAC_ACCESS_DENIED
 * even with an active, verified care relationship on the mother — confirmed
 * live 2026-08-22. Clinical's `EnforceCareRelationship` middleware resolves
 * "the patient" from a `{patientId}` route parameter or a bound model's
 * `patient_id`/`patientIdentifier()`; the apgar route only binds
 * `{birthRecord}`, whose model has no such identifier (only
 * `infant_patient_id`, null until the infant is registered) — the
 * middleware very likely resolves no patient at all and fails the gate
 * rather than skipping it. Reported for Clinical's side to fix; store(),
 * forPatient() and show() are all confirmed working.
 *
 * A raw array, not a DTO — the birth event/record shape is rich and
 * display-only.
 */
interface MaternityGateway
{
    /**
     * @return array<string, array<int, array<string, mixed>>> keyed by option_type: DELIVERY_MODE, PRESENTATION, MATERNAL_OUTCOME
     */
    public function options(ClinicalActor $actor): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(ClinicalActor $actor, string $motherPatientId): array;

    /**
     * @param  array<int, array{sex: string, birth_outcome: string, birth_weight_value?: float, birth_weight_uom_id?: int}>  $infants
     * @return array<string, mixed>
     */
    public function recordBirth(
        ClinicalActor $actor,
        string $motherPatientId,
        string $motherVisitId,
        string $deliveryAt,
        string $deliveryModeCode,
        array $infants,
        ?float $gestationWeeks = null,
        ?string $presentationCode = null,
        ?string $maternalOutcomeCode = null,
        ?string $deliveryNotes = null,
    ): array;

    /**
     * @param  array<string, string>  $components  APPEARANCE, PULSE, GRIMACE, ACTIVITY, RESPIRATION
     * @return array<string, mixed>
     */
    public function recordApgar(ClinicalActor $actor, int|string $birthRecordId, int $timepointMinutes, array $components): array;
}
