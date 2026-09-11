<?php

namespace App\Services\Imaging;

use App\Models\ImagingStudy;
use App\Models\ImagingStudyWorkflowExecution;
use App\Models\ImagingWorkflowStepExecution;
use App\Models\ProtocolWorkflowStep;

/**
 * RIS Amendment v2.6, Chunk 3. Replaces ImagingStudy's hardcoded status
 * machine as the actual driver of study progression — ImagingStudy's own
 * markPreparationRequired() through markVerified() are now thin
 * compatibility wrappers around completeStep() below, so every existing
 * caller (ImagingReportObserver, ImagingStudyController, RecoveryRecord)
 * needs zero changes.
 */
class WorkflowEngineService
{
    public function __construct(
        private readonly WorkflowStatusMapperService $statusMapper,
        private readonly ConsumptionAttributionService $consumption,
        private readonly ImagingAuditService $auditService,
    ) {}

    /**
     * Called from ImagingOrder::acceptIntoStudy() for a brand-new study —
     * always positions at the workflow's first step, since a new study
     * genuinely starts fresh (unlike resolveOrStartExecution()'s
     * backward-compatibility case below).
     */
    public function startWorkflow(ImagingStudy $study): ImagingStudyWorkflowExecution
    {
        $workflow = $study->protocol()?->activeWorkflow();

        if (! $workflow) {
            throw new \RuntimeException("No active workflow configured for protocol [{$study->protocol_code}].");
        }

        $firstStep = $workflow->steps()->orderBy('sequence_no')->first();

        if (! $firstStep) {
            throw new \RuntimeException("Workflow [{$workflow->workflow_name}] (protocol {$study->protocol_code}) has no steps configured.");
        }

        $execution = ImagingStudyWorkflowExecution::create([
            'imaging_study_id' => $study->id,
            'imaging_protocol_workflow_id' => $workflow->id,
            'current_protocol_workflow_step_id' => $firstStep->id,
            'status' => ImagingStudyWorkflowExecution::STATUS_ACTIVE,
        ]);

        $this->auditService->log(ImagingAuditService::ACTION_STEP_STARTED, $study, [
            'workflow_version' => $workflow->workflow_version,
            'workflow_step_id' => $firstStep->imaging_workflow_step_id,
            'ris_status' => $firstStep->ris_status,
        ]);

        return $execution;
    }

    public function getCurrentStep(ImagingStudy $study): ?ProtocolWorkflowStep
    {
        return $this->activeExecution($study)?->currentStep;
    }

    /**
     * Public entry point for WorkflowOwnershipService (Chunk 4) to get the
     * execution it needs to claim/release against — reuses the same lazy
     * backfill as completeStep() below, so claiming works identically for
     * a pre-Chunk-3 study as for one created after.
     */
    public function resolveExecution(ImagingStudy $study): ImagingStudyWorkflowExecution
    {
        return $this->resolveOrStartExecution($study);
    }

    /**
     * Records history, updates $study->status/main_module_status to the
     * target step's mapping, and moves the execution's current-step
     * pointer to it directly (found by ris_status, not "current + 1") —
     * this is what lets a protocol configured to skip Preparation
     * (ImagingProtocol::requires_preparation, built before this amendment)
     * jump straight from Order Received to Ready For Study without the
     * engine treating that as an invalid non-sequential move. The calling
     * ImagingStudy::mark*() method is what actually enforces which
     * transitions are legal (via its existing requireStatus() guard,
     * unchanged) — this method trusts the caller and just applies it.
     */
    public function completeStep(
        ImagingStudy $study,
        string $targetRisStatus,
        ?int $userId = null,
        array $extra = [],
        ?string $notes = null,
        ?int $roomId = null,
    ): ProtocolWorkflowStep {
        $execution = $this->resolveOrStartExecution($study);
        $workflow = $execution->protocolWorkflow;

        $targetStep = $workflow->steps()->where('ris_status', $targetRisStatus)->first();

        if (! $targetStep) {
            throw new \RuntimeException("Workflow [{$workflow->workflow_name}] has no step mapped to RIS status [{$targetRisStatus}].");
        }

        ImagingWorkflowStepExecution::create([
            'imaging_study_workflow_execution_id' => $execution->id,
            'imaging_protocol_workflow_step_id' => $targetStep->id,
            'executed_by' => $userId,
            'room_id' => $roomId,
            'started_at' => now(),
            'completed_at' => now(),
            'notes' => $notes,
        ]);

        $this->statusMapper->updateRisStatus($study, $targetStep->ris_status, $userId, $extra);
        $this->statusMapper->updateMainStatus($study, $targetStep->main_status);

        $maxSequence = $workflow->steps()->max('sequence_no');

        $execution->update([
            'current_protocol_workflow_step_id' => $targetStep->id,
            'status' => $targetStep->sequence_no >= $maxSequence
                ? ImagingStudyWorkflowExecution::STATUS_COMPLETED
                : ImagingStudyWorkflowExecution::STATUS_ACTIVE,
        ]);

        // Chunk 4: whoever claimed the step just completed is done with it —
        // the new current step (even if claimed by the same person) starts
        // unclaimed, so My Queue shows it as available rather than silently
        // "still held" by a claim that was really for the prior step.
        \App\Models\ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)
            ->active()
            ->update(['released_at' => now()]);

        $this->auditService->log(ImagingAuditService::ACTION_STEP_COMPLETED, $study, [
            'workflow_version' => $workflow->workflow_version,
            'workflow_step_id' => $targetStep->imaging_workflow_step_id,
            'ris_status' => $targetStep->ris_status,
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        // RIS Amendment v2.6, Chunk 6: triggers_consumption (set per-step in
        // the workflow composition UI) replaces ImagingProtocol::consumption_trigger_status
        // as the trigger source for every case that maps onto a real
        // workflow step — the backfilled default workflow set this flag on
        // exactly the step each protocol's old consumption_trigger_status
        // pointed at, so this fires at the identical point it always did
        // for every existing protocol.
        if ($targetStep->triggers_consumption) {
            $this->consumption->triggerConsumption($study, $targetStep, $userId);
        }

        return $targetStep;
    }

    /**
     * Completes whatever the current step is, advancing to the next one
     * in sequence with no specific target in mind — for callers (Chunk 4's
     * queue UI) that just want to move a study forward one slot, as
     * opposed to the old-status-shim callers of completeStep() above,
     * which always know exactly which status they're targeting.
     */
    public function advanceToNextStep(ImagingStudy $study, ?int $userId = null): ?ProtocolWorkflowStep
    {
        $current = $this->getCurrentStep($study);

        if (! $current) {
            return null;
        }

        $next = ProtocolWorkflowStep::where('imaging_protocol_workflow_id', $current->imaging_protocol_workflow_id)
            ->where('sequence_no', '>', $current->sequence_no)
            ->orderBy('sequence_no')
            ->first();

        if (! $next) {
            return null;
        }

        return $this->completeStep($study, $next->ris_status, $userId);
    }

    protected function activeExecution(ImagingStudy $study): ?ImagingStudyWorkflowExecution
    {
        return ImagingStudyWorkflowExecution::where('imaging_study_id', $study->id)
            ->active()
            ->latest()
            ->first();
    }

    /**
     * Backward compatibility for a study that existed before this chunk
     * shipped (no execution row at all yet): lazily creates one positioned
     * at whichever step matches the study's CURRENT status, so it
     * continues from where it already is instead of restarting at step 1.
     */
    protected function resolveOrStartExecution(ImagingStudy $study): ImagingStudyWorkflowExecution
    {
        $execution = $this->activeExecution($study);

        if ($execution) {
            return $execution;
        }

        $workflow = $study->protocol()?->activeWorkflow();

        if (! $workflow) {
            throw new \RuntimeException("No active workflow configured for protocol [{$study->protocol_code}].");
        }

        $currentStep = $workflow->steps()->where('ris_status', $study->status)->first()
            ?? $workflow->steps()->orderBy('sequence_no')->first();

        if (! $currentStep) {
            throw new \RuntimeException("Workflow [{$workflow->workflow_name}] has no steps configured.");
        }

        return ImagingStudyWorkflowExecution::create([
            'imaging_study_id' => $study->id,
            'imaging_protocol_workflow_id' => $workflow->id,
            'current_protocol_workflow_step_id' => $currentStep->id,
            'status' => ImagingStudyWorkflowExecution::STATUS_ACTIVE,
        ]);
    }
}
