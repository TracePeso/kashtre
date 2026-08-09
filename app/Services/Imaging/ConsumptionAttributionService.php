<?php

namespace App\Services\Imaging;

use App\Models\ImagingConsumptionException;
use App\Models\ImagingStudy;
use App\Models\ProtocolWorkflowStep;

/**
 * RIS Amendment v2.6, Chunk 6. A thin wrapper around the existing
 * RadiologyRecipeEngine — triggerConsumption() calls its depleteForStudy(),
 * resolveStore() calls its resolveStoreId() — plus the genuinely new
 * piece: createException()/resolveException(), giving a resolvable audit
 * trail for a consumption attempt that couldn't actually deplete stock,
 * where before it was either a silent no-op or a log line nobody acted on.
 */
class ConsumptionAttributionService
{
    public function __construct(
        private readonly RadiologyRecipeEngine $recipeEngine,
        private readonly ImagingAuditService $auditService,
    ) {}

    /**
     * Called from WorkflowEngineService::completeStep() when the step just
     * reached has triggers_consumption set — replaces
     * ImagingProtocol::consumption_trigger_status as the trigger source for
     * every case that maps onto a real workflow step. The one case that
     * doesn't (RecoveryRecord's RECOVERY_COMPLETE hook, since recovery
     * discharge isn't itself a step) still calls
     * ImagingStudy::triggerConsumptionIfDue() directly, unchanged — there's
     * no step to attribute that trigger to.
     */
    public function triggerConsumption(ImagingStudy $study, ?ProtocolWorkflowStep $step = null, ?int $userId = null): void
    {
        if ($study->is_consumption_executed) {
            return;
        }

        $this->auditService->log(ImagingAuditService::ACTION_CONSUMPTION_TRIGGERED, $study, [
            'workflow_step_id' => $step?->imaging_workflow_step_id,
            'imaging_protocol_workflow_step_id' => $step?->id,
            'user_id' => $userId,
        ]);

        // RadiologyRecipeEngine creates an ImagingConsumptionException row
        // directly (not through this service — that would be a circular
        // dependency, this class wraps that engine, not the other way
        // around) for any recipe line it couldn't actually deplete. Diffing
        // by id before/after is how this call site finds out, since
        // depleteForStudy() itself has no return value to report through.
        $maxExceptionIdBefore = (int) (ImagingConsumptionException::where('imaging_study_id', $study->id)->max('id') ?? 0);

        $this->recipeEngine->depleteForStudy($study, $userId, $step);

        $newExceptions = ImagingConsumptionException::where('imaging_study_id', $study->id)
            ->where('id', '>', $maxExceptionIdBefore)
            ->get();

        if ($newExceptions->isNotEmpty()) {
            $this->auditService->log(ImagingAuditService::ACTION_CONSUMPTION_FAILED, $study, [
                'workflow_step_id' => $step?->imaging_workflow_step_id,
                'imaging_protocol_workflow_step_id' => $step?->id,
                'exception_count' => $newExceptions->count(),
                'reasons' => $newExceptions->pluck('exception_reason')->all(),
            ]);
        }

        $study->update([
            'is_consumption_executed' => true,
            'consumption_executed_at' => now(),
        ]);
    }

    public function resolveStore(int $businessId, int $itemId, ?int $userId, ?int $mainRoomId = null): ?int
    {
        return $this->recipeEngine->resolveStoreId($businessId, $itemId, $userId, $mainRoomId);
    }

    public function createException(ImagingStudy $study, ?ProtocolWorkflowStep $step, string $reason): ImagingConsumptionException
    {
        return ImagingConsumptionException::create([
            'imaging_study_id' => $study->id,
            'imaging_protocol_workflow_step_id' => $step?->id,
            'exception_reason' => $reason,
        ]);
    }

    public function resolveException(ImagingConsumptionException $exception, int $resolvedByUserId): void
    {
        $exception->update([
            'resolved' => true,
            'resolved_by_user_id' => $resolvedByUserId,
            'resolved_at' => now(),
        ]);
    }
}
