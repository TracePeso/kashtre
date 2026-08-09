<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Care relationships (ReBAC) and break-glass — API Integration Guide §10.1,
 * and gates 3 and 4 of §8.
 *
 * Important: under CLINICAL_DRIVER=api these methods are *advisory*. Clinical
 * enforces the care-relationship gate itself on every patient-scoped call and
 * will return 403 REBAC_ACCESS_DENIED regardless of what this gateway said.
 * Use it to decide what to render — greying out a button the clinician cannot
 * use — never as the enforcement point. Enforcement that lives in the client
 * is not enforcement.
 */
interface CareAccessGateway
{
    /**
     * Does this clinician have a live care relationship with this patient,
     * by individual, role, team or hybrid assignment?
     */
    public function hasActiveRelationship(ClinicalActor $actor, string $patientId): bool;

    /**
     * Every patient this clinician is responsible for — the "My Patients"
     * set that filters enterprise-wide worklists down to something a person
     * can act on.
     *
     * @return array<int, string> global_client_id values
     */
    public function myPatientIds(ClinicalActor $actor): array;

    /**
     * Assigns the acting clinician to a patient in a named capacity.
     *
     * @param  string  $role  'doctor' or 'nurse'
     */
    public function claim(ClinicalActor $actor, string $role, string $patientId, ?string $visitId = null): void;

    /**
     * Emergency override when no care relationship exists. Grants an audited,
     * time-boxed window and writes to the immutable audit trail — the API
     * grants four hours, the local guard defaults to fifteen minutes.
     *
     * Only offer this when a refusal actually said it would help
     * (`requires_break_glass: true`); otherwise the refusal came from a
     * different gate and break-glass will not lift it.
     */
    public function grantBreakGlass(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $reasonCode,
        ?string $justificationNote = null,
    ): void;

    /**
     * Whether live mutations (prescribing, MAR administration, finalising a
     * discharge) are permitted from where this request originated.
     *
     * Off-premises, §8 restricts callers to chart review and draft completion.
     */
    public function canMutateFromCurrentLocation(): bool;
}
