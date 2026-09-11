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
     * May this clinician open this patient's chart — by individual, role, team
     * or hybrid assignment, or under an unexpired break-glass grant?
     *
     * The override is included deliberately: callers use this to decide whether
     * to show the chart or the refusal screen, and a clinician who has just
     * broken glass would otherwise be sent straight back to the refusal.
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
     *
     * @return string|null the granted BreakGlassEpisode's public_id (v6.1
     *                      Volume 9) — previously unobtainable anywhere
     *                      (confirmed live 2026-09-05: the episode is created
     *                      on every grant, but nothing returned its id).
     *                      Null under the local driver, which has no
     *                      BreakGlassEpisode concept.
     */
    public function grantBreakGlass(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $reasonCode,
        ?string $justificationNote = null,
    ): ?string;

    /**
     * Whether live mutations (prescribing, MAR administration, finalising a
     * discharge) are permitted from where this request originated.
     *
     * Off-premises, §8 restricts callers to chart review and draft completion.
     */
    public function canMutateFromCurrentLocation(): bool;

    /**
     * Who currently holds clinical responsibility for a patient — the fuller
     * picture behind hasActiveRelationship()'s yes/no: assignment model,
     * PRIMARY vs CO_MANAGING participation, team, and every member with a
     * live claim. Raw array (not a DTO) because it is display-only and
     * Clinical's own shape is already well-structured; a DTO here would
     * mean chasing field-name drift for no behavioural gain. See
     * `clinical/patients/{id}/care-team`, confirmed live 2026-08-18.
     *
     * @return array<string, mixed>
     */
    public function teamFor(ClinicalActor $actor, string $patientId, ?string $visitId = null): array;

    /**
     * Assigns clinical responsibility. A fresh PARTICIPATION_PRIMARY
     * supersedes whoever holds it now — this *is* the "change of care" /
     * handover act, not a separate endpoint. PARTICIPATION_CO_MANAGING adds
     * a specialty alongside the primary without displacing them.
     *
     * @param  array{patient_id: string, visit_id: string, assignment_model: string, participation?: string, primary_doctor_id?: ?int, primary_nurse_id?: ?int, assigned_team_id?: ?int, assigned_role_code?: ?string}  $attributes
     */
    public function assign(ClinicalActor $actor, array $attributes): void;

    /**
     * Ends one assignment — the clinician (or team) named on it is no
     * longer responsible. Ending the PRIMARY assignment does not remove any
     * CO_MANAGING participants; each is its own row, ended independently.
     */
    public function endAssignment(ClinicalActor $actor, int|string $assignmentId): void;

    /**
     * Independent retrospective review of a break-glass episode — v6.1
     * Volume 9's genuinely new capability over the old break-glass grant,
     * which had no review lifecycle at all. `$episodePublicId` is what
     * `grantBreakGlass()` now returns.
     *
     * @param  string  $outcome  JUSTIFIED | UNJUSTIFIED | INCONCLUSIVE
     */
    public function reviewBreakGlass(
        ClinicalActor $actor,
        string $episodePublicId,
        string $outcome,
        string $finding,
    ): void;
}
