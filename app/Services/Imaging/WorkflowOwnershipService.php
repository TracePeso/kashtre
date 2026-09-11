<?php

namespace App\Services\Imaging;

use App\Models\ImagingStudy;
use App\Models\ImagingStudyWorkflowExecution;
use App\Models\ImagingWorkflowClaim;
use App\Models\ImagingWorkflowStep;

/**
 * RIS Amendment v2.6, Chunk 4. Claim/release/transfer ownership of a study
 * at its current workflow step — the mechanism behind the My Queue page.
 * Eligibility is gated by the step's assigned user pool
 * (ImagingWorkflowStep::users(), Chunk 1's registry), with one deliberate
 * fallback: an empty pool means "not yet configured", not "nobody
 * eligible" — every user holding the base permission stays eligible until
 * an admin actually assigns a pool, the same "empty = eligible" rule
 * already used for ImagingModuleConfig's peer-review reviewer pool. This
 * is what keeps existing businesses' worklists working on day one, before
 * anyone has visited Manage Workflow Steps > Assign Users.
 */
class WorkflowOwnershipService
{
    public function __construct(
        private readonly WorkflowEngineService $engine,
        private readonly ImagingAuditService $auditService,
    ) {}

    /**
     * True whenever $userId may act on $step's current queue — either
     * because they're actually in its pool, or because the pool is empty
     * (not configured yet).
     */
    public function isEligibleForStep(ImagingWorkflowStep $step, int $userId): bool
    {
        return $step->users()->count() === 0 || $step->users()->where('users.id', $userId)->exists();
    }

    /**
     * Claims the study's current step for $userId. Idempotent if $userId
     * already holds the active claim; throws if someone else does, if the
     * step's workflow has already finished (CANCELLED execution), or if
     * $userId isn't eligible for the current step's pool.
     */
    public function claimStudy(ImagingStudy $study, int $userId): ImagingWorkflowClaim
    {
        $execution = $this->engine->resolveExecution($study);

        if ($execution->status === ImagingStudyWorkflowExecution::STATUS_CANCELLED) {
            throw new \RuntimeException("Study {$study->accession_number}'s workflow has been cancelled and can no longer be claimed.");
        }

        $step = $execution->currentStep;
        $workflowStep = $step->workflowStep;

        if (! $this->isEligibleForStep($workflowStep, $userId)) {
            throw new \RuntimeException("You are not assigned to the [{$workflowStep->step_name}] queue.");
        }

        $active = ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)->active()->first();

        if ($active) {
            if ($active->assigned_user_id === $userId) {
                return $active;
            }

            throw new \RuntimeException("Study {$study->accession_number} is already claimed by another user.");
        }

        $claim = ImagingWorkflowClaim::create([
            'imaging_study_workflow_execution_id' => $execution->id,
            'imaging_protocol_workflow_step_id' => $step->id,
            'assigned_user_id' => $userId,
            'claimed_at' => now(),
        ]);

        $this->auditService->log(ImagingAuditService::ACTION_STUDY_CLAIMED, $study, [
            'workflow_step_id' => $workflowStep->id,
            'imaging_protocol_workflow_step_id' => $step->id,
            'user_id' => $userId,
        ]);

        return $claim;
    }

    /**
     * Releases the study's active claim, if any — a no-op if it's already
     * unclaimed. $userId, when given, must match the claim holder (a plain
     * self-service release); pass null for an admin/override release.
     */
    public function releaseStudy(ImagingStudy $study, ?int $userId = null): void
    {
        $execution = $this->engine->resolveExecution($study);

        $active = ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)->active()->first();

        if (! $active) {
            return;
        }

        if ($userId !== null && $active->assigned_user_id !== $userId) {
            throw new \RuntimeException("Study {$study->accession_number} is claimed by another user.");
        }

        $active->update(['released_at' => now()]);

        $this->auditService->log(ImagingAuditService::ACTION_STUDY_RELEASED, $study, [
            'imaging_protocol_workflow_step_id' => $active->imaging_protocol_workflow_step_id,
            'user_id' => $userId ?? $active->assigned_user_id,
        ]);
    }

    /**
     * Hands the active claim to $toUserId, who must themselves be eligible
     * for the current step. Releases whoever held it (regardless of who
     * that was) and creates a fresh claim — a transfer, not a request.
     */
    public function transferStudy(ImagingStudy $study, int $toUserId): ImagingWorkflowClaim
    {
        $execution = $this->engine->resolveExecution($study);
        $step = $execution->currentStep;
        $workflowStep = $step->workflowStep;

        if (! $this->isEligibleForStep($workflowStep, $toUserId)) {
            throw new \RuntimeException("That user is not assigned to the [{$workflowStep->step_name}] queue.");
        }

        $previous = ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)->active()->first();

        ImagingWorkflowClaim::where('imaging_study_workflow_execution_id', $execution->id)
            ->active()
            ->update(['released_at' => now()]);

        if ($previous) {
            $this->auditService->log(ImagingAuditService::ACTION_STUDY_RELEASED, $study, [
                'imaging_protocol_workflow_step_id' => $previous->imaging_protocol_workflow_step_id,
                'user_id' => $previous->assigned_user_id,
            ]);
        }

        $claim = ImagingWorkflowClaim::create([
            'imaging_study_workflow_execution_id' => $execution->id,
            'imaging_protocol_workflow_step_id' => $step->id,
            'assigned_user_id' => $toUserId,
            'claimed_at' => now(),
        ]);

        $this->auditService->log(ImagingAuditService::ACTION_STUDY_CLAIMED, $study, [
            'workflow_step_id' => $workflowStep->id,
            'imaging_protocol_workflow_step_id' => $step->id,
            'user_id' => $toUserId,
        ]);

        return $claim;
    }
}
