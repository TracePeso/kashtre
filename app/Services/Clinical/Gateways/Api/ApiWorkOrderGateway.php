<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\WorkOrderGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CLINICAL_DRIVER=api: `clinical/work-orders*` and
 * `clinical/patients/{id}/work-orders` (confirmed live 2026-08-19).
 */
class ApiWorkOrderGateway implements WorkOrderGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/work-orders",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load this patient\'s work orders.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
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
        return $this->client->post(
            'clinical/work-orders',
            array_filter([
                'patient_id' => $patientId,
                'visit_id' => $visitId,
                'order_name' => $orderName,
                'assigned_to_user_id' => $assignedToUserId,
                'assigned_role_code' => $assignedRoleCode,
                'notes' => $notes,
            ], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => (string) Str::uuid(),
            ],
        );
    }

    public function transition(ClinicalActor $actor, int|string $workOrderId, string $status, ?string $notes = null): array
    {
        return $this->client->post(
            "clinical/work-orders/{$workOrderId}/transition",
            array_filter(['status' => $status, 'notes' => $notes], fn ($v) => $v !== null),
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => 'work-order-'.$workOrderId.'-'.$status,
            ],
        );
    }
}
