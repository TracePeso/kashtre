<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\AuditTrailGateway;
use App\Models\ClinicalBreakGlassLog;
use App\Models\ClinicalProcessStepExecution;
use App\Models\User;
use App\Support\Clinical\AuditTrailEntry;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: merges the two ledgers this driver actually writes
 * (break-glass grants, process-step completions) into one stream sorted by
 * time, same shape AuditTrail's view already expected before this gateway
 * existed.
 */
class LocalAuditTrailGateway implements AuditTrailGateway
{
    public function forPatient(ClinicalActor $actor, string $patientId, int $limit = 20): array
    {
        $breakGlass = ClinicalBreakGlassLog::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $steps = ClinicalProcessStepExecution::whereHas('execution', function ($query) use ($actor, $patientId) {
            $query->where('business_id', $actor->businessId)->where('client_id', $patientId);
        })
            ->with('step')
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get();

        $actorIds = $breakGlass->pluck('user_id')
            ->merge($steps->pluck('completed_by_user_id'))
            ->filter()
            ->unique();

        $names = User::whereIn('id', $actorIds)->pluck('name', 'id');

        $entries = $breakGlass->map(fn (ClinicalBreakGlassLog $log) => new AuditTrailEntry(
            id: 'bg-'.$log->id,
            action: 'BREAK_GLASS_GRANTED',
            patientId: $log->client_id,
            actorUserId: $log->user_id,
            actorName: $names->get($log->user_id),
            actorRoles: [],
            context: [
                'reason_code' => $log->reason_code,
                'justification_note' => $log->justification_note,
                'granted_until' => $log->granted_until?->toIso8601String(),
                'visit_id' => $log->visit_id,
            ],
            createdAt: $log->created_at?->toIso8601String(),
        ))->merge($steps->map(fn (ClinicalProcessStepExecution $execution) => new AuditTrailEntry(
            id: 'step-'.$execution->id,
            action: $execution->status === ClinicalProcessStepExecution::STATUS_SKIPPED
                ? 'PROCESS_STEP_SKIPPED' : 'PROCESS_STEP_COMPLETED',
            patientId: $patientId,
            actorUserId: $execution->completed_by_user_id,
            actorName: $names->get($execution->completed_by_user_id),
            actorRoles: [],
            context: array_filter([
                'step_name' => $execution->step?->step_name,
                'notes' => $execution->notes,
                'override_reason' => $execution->override_reason,
            ]),
            createdAt: $execution->completed_at?->toIso8601String(),
        )));

        return $entries
            ->sortByDesc(fn (AuditTrailEntry $e) => $e->createdAt)
            ->take($limit)
            ->values()
            ->all();
    }
}
