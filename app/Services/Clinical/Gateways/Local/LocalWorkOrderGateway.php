<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\WorkOrderGateway;
use App\Models\ClinicalWorkOrder;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: `clinical_work_orders`, the same table
 * PlaceLabOrder/PlaceDiagnosticOrder already read. That table has no
 * dedicated free-text task-name column — `external_reference` is
 * repurposed to carry it here, since nothing else in this schema holds a
 * clinician-typed task description.
 */
class LocalWorkOrderGateway implements WorkOrderGateway
{
    private const TYPE_AD_HOC = 'AD_HOC_TASK';

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        return ClinicalWorkOrder::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ClinicalWorkOrder $wo) => [
                'id' => $wo->id,
                'patient_id' => $wo->client_id,
                'visit_id' => $wo->visit_id,
                'order_type' => $wo->order_type,
                'order_name' => $wo->order_type === self::TYPE_AD_HOC ? $wo->external_reference : $wo->order_type,
                'assigned_to_user_id' => $wo->assigned_to_user_id,
                'assigned_role_code' => $wo->assigned_role_code,
                'status' => $wo->status,
                'created_at' => $wo->created_at?->toIso8601String(),
                'completed_at' => $wo->completed_at?->toIso8601String(),
            ])
            ->all();
    }

    public function create(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $orderName,
        ?int $assignedToUserId = null,
        ?string $assignedRoleCode = null,
        ?string $notes = null,
    ): array {
        $wo = ClinicalWorkOrder::create([
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $visitId,
            'order_type' => self::TYPE_AD_HOC,
            'ordering_user_id' => $actor->userId,
            'assigned_to_user_id' => $assignedToUserId,
            'assigned_role_code' => $assignedRoleCode,
            'status' => ClinicalWorkOrder::STATUS_PENDING,
            'external_reference' => $orderName,
            'created_at' => now(),
        ]);

        return [
            'id' => $wo->id,
            'patient_id' => $wo->client_id,
            'visit_id' => $wo->visit_id,
            'order_type' => $wo->order_type,
            'order_name' => $orderName,
            'status' => $wo->status,
            'created_at' => $wo->created_at?->toIso8601String(),
        ];
    }

    public function transition(ClinicalActor $actor, int|string $workOrderId, string $status, ?string $notes = null): array
    {
        $wo = ClinicalWorkOrder::where('business_id', $actor->businessId)->findOrFail($workOrderId);

        $wo->update([
            'status' => $status,
            'completed_at' => $status === ClinicalWorkOrder::STATUS_COMPLETED ? now() : $wo->completed_at,
        ]);

        return [
            'id' => $wo->id,
            'status' => $wo->status,
            'completed_at' => $wo->completed_at?->toIso8601String(),
        ];
    }
}
