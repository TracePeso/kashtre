<?php

namespace App\Support\Clinical;

use App\Models\ClinicalProcessExecution;

/**
 * One run of a major clinical transition (Admission, Transfer, Discharge, …)
 * against a patient — SRD §4.3.
 *
 * Local calls this a `ClinicalProcessExecution`; Clinical calls it a
 * "transition instance" (`GET/POST clinical/transitions/...`, confirmed live
 * against the real service 2026-08-15). Same concept, different vocabulary —
 * this DTO is the shape a panel is written against either way.
 */
class ProcessInstance
{
    /**
     * @param  array{step_code: string, step_name: string, step_order: int, required_role: ?string, is_mandatory: bool}|null  $nextStep
     * @param  array<int, array<string, mixed>>  $completions
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $processCode,
        public readonly string $patientId,
        public readonly ?string $visitId,
        public readonly string $status,
        public readonly ?string $startedAt,
        public readonly ?string $completedAt,
        public readonly ?array $nextStep = null,
        public readonly array $completions = [],
        public readonly ?string $abandonmentReasonCode = null,
        /** decisionToAdmit() only: the bed code held, if one was named. */
        public readonly ?string $bedReserved = null,
        /** decisionToAdmit() only: true when the receiving ward still has to find a bed. */
        public readonly ?bool $requiresBedAllocation = null,
        /**
         * executeStep() only: the completed step's own effects — carries
         * movement_id for BED_ALLOCATION/BED_CUSTODY_TRANSFER, which Care
         * Transitions (v6.1 §1) needs for "Complete an internal transfer".
         * Previously discarded entirely even though the API already returned
         * it under `effects` (confirmed live 2026-09-05).
         *
         * @var array<string, mixed>|null
         */
        public readonly ?array $stepEffects = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'IN_PROGRESS';
    }

    public static function fromModel(ClinicalProcessExecution $execution): self
    {
        $step = $execution->currentStep;

        return new self(
            id: $execution->id,
            processCode: (string) ($execution->process?->process_code ?? ''),
            patientId: (string) $execution->client_id,
            visitId: $execution->visit_id ? (string) $execution->visit_id : null,
            // Local has no ABANDONED state, only CANCELLED — normalised here
            // so a panel checking isActive()/status once covers both drivers.
            status: $execution->status === ClinicalProcessExecution::STATUS_CANCELLED
                ? 'ABANDONED' : $execution->status,
            startedAt: $execution->started_at?->toIso8601String(),
            completedAt: $execution->completed_at?->toIso8601String(),
            nextStep: $step ? [
                'step_code' => $step->step_code,
                'step_name' => $step->step_name,
                'step_order' => $step->step_order,
                'required_role' => $step->required_role,
                'is_mandatory' => (bool) $step->is_mandatory,
            ] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? $payload['instance_id'] ?? 0,
            processCode: (string) ($payload['process_code'] ?? ''),
            patientId: (string) ($payload['patient_id'] ?? ''),
            visitId: isset($payload['visit_id']) ? (string) $payload['visit_id'] : null,
            status: (string) ($payload['status'] ?? $payload['transition_status'] ?? ''),
            startedAt: $payload['started_at'] ?? null,
            completedAt: $payload['completed_at'] ?? null,
            nextStep: $payload['next_step'] ?? null,
            completions: $payload['completions'] ?? [],
            abandonmentReasonCode: $payload['abandonment_reason_code'] ?? null,
            bedReserved: $payload['bed_reserved'] ?? null,
            requiresBedAllocation: array_key_exists('requires_bed_allocation', $payload)
                ? (bool) $payload['requires_bed_allocation'] : null,
        );
    }
}
