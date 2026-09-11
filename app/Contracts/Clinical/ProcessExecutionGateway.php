<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ProcessInstance;

/**
 * Running a major clinical transition (Admission, Transfer, Discharge, …)
 * against a patient — SRD §4.3. The *catalogue* of available processes and
 * their steps is a dictionary (`ClinicalSettingsGateway`, path
 * `settings/process-registry`); this gateway is the *execution* of one
 * against a real patient, which is a separate concept under both drivers.
 *
 * Confirmed against Clinical's own ClinicalProcessEngine/ProcessWorkflowController
 * source 2026-08-19 — corrects an earlier, wrong assumption made from partial
 * live probing (there IS a real per-step skip; the earlier interface routed
 * every "doesn't apply" case through abandon() instead, which closes the
 * whole instance rather than one step).
 *
 * A step can refuse for three independent reasons — mandatory and skipped,
 * wrong role, or an unmet `completion_rule` — and any of them can be
 * overridden with an audited reason (category PROCESS_OVERRIDE). The
 * refusal always names which: catch `ClinicalRuleRefusedException` and
 * check `isProcessStepBlocked()` / `blockedBy()`.
 */
interface ProcessExecutionGateway
{
    /**
     * Active instance(s) plus recent history for one patient.
     *
     * @return array<int, ProcessInstance>
     */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    public function start(
        ClinicalActor $actor,
        string $processCode,
        string $patientId,
        ?string $visitId,
        ?string $initiationNote = null,
    ): ProcessInstance;

    /**
     * $skip records the step as SKIPPED instead of COMPLETED — no side
     * effects (a skipped ALLOCATE_BED step reserves nothing) — and, like a
     * blocked completion, needs an override reason if the step is
     * mandatory.
     *
     * The remaining parameters are each meaningful to exactly one or two
     * step codes — confirmed against Clinical's own TransitionStepEffects
     * source 2026-08-22, which is the ground truth this was built from, not
     * a guess:
     *   $bedId       — BED_ALLOCATION (required), TRANSFER_REQUEST
     *                  (optional — a destination named later still works),
     *                  BED_CUSTODY_TRANSFER (required, falls back to the
     *                  bed TRANSFER_REQUEST already reserved).
     *   $lockNote    — DEATH_CERTIFICATE_SIGN_OFF only.
     *   $exportFormat — REFERRAL_SIGN_OFF only (FHIR_JSON default | FHIR_XML).
     * Every other step code (most of them — "documentation" steps per the
     * guide) takes none of these; sending them is harmless, Clinical's
     * effect dispatch on step_code simply never reads them.
     */
    public function executeStep(
        ClinicalActor $actor,
        int|string $instanceId,
        string $stepCode,
        bool $skip = false,
        ?string $completionNote = null,
        ?int $bedId = null,
        ?string $overrideReasonCode = null,
        ?string $overrideNote = null,
        ?string $lockNote = null,
        ?string $exportFormat = null,
    ): ProcessInstance;

    public function abandon(
        ClinicalActor $actor,
        int|string $instanceId,
        string $reasonCode,
        ?string $note = null,
    ): ProcessInstance;

    /**
     * SRD §4.2's clinical-intent bridge: starts ADMISSION and, when a bed is
     * named, reserves it before the patient physically arrives. Emits
     * ADMISSION_REQUESTED to Main either way — `requiresBedAllocation()` on
     * the result tells the receiving ward whether it still has to find one.
     */
    public function decisionToAdmit(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $targetWardCode,
        ?string $targetSpecialty = null,
        ?string $admissionNote = null,
        ?int $bedId = null,
    ): ProcessInstance;
}
