<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\MedicationReconciliationGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: medication reconciliation over HTTP — SRD v6.1
 * Phase 6, API_GUIDE_V6.1 §Phase 6.
 */
class ApiMedicationReconciliationGateway implements MedicationReconciliationGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function start(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array
    {
        return $this->client->post('clinical/medication-reconciliations', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'reconciliation_type' => $payload['reconciliation_type'],
            'performed_by_user_id' => $payload['performed_by_user_id'],
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function show(ClinicalActor $actor, string $reconciliationId): array
    {
        return $this->client->get(
            "clinical/medication-reconciliations/{$reconciliationId}",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function addItem(ClinicalActor $actor, string $reconciliationId, array $item): array
    {
        return $this->client->post(
            "clinical/medication-reconciliations/{$reconciliationId}/items",
            array_filter([
                'source' => $item['source'],
                'medication_name' => $item['medication_name'],
                'dose' => $item['dose'] ?? null,
            ], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function decideItem(ClinicalActor $actor, string $itemId, string $decision, int $decidedByUserId): array
    {
        return $this->client->post(
            "clinical/medication-reconciliation-items/{$itemId}/decision",
            ['decision' => $decision, 'decided_by_user_id' => $decidedByUserId],
            ['business_id' => $actor->businessId],
        );
    }

    public function complete(ClinicalActor $actor, string $reconciliationId): array
    {
        return $this->client->post(
            "clinical/medication-reconciliations/{$reconciliationId}/complete",
            [],
            ['business_id' => $actor->businessId],
        );
    }
}
