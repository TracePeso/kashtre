<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\MedicationAdverseEventGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: medication adverse-event reporting over HTTP —
 * SRD v6.1 Phase 6, API_GUIDE_V6.1 §Phase 6.
 */
class ApiMedicationAdverseEventGateway implements MedicationAdverseEventGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function report(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array
    {
        return $this->client->post('clinical/medication-adverse-events', array_filter([
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'event_type' => $payload['event_type'],
            'description' => $payload['description'],
            'severity' => $payload['severity'],
            'reported_by_user_id' => $payload['reported_by_user_id'],
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get(
            'clinical/medication-adverse-events',
            ['patient_id' => $patientId],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function recordResponse(ClinicalActor $actor, string $eventId, string $clinicalResponse, ?string $outcome = null): array
    {
        return $this->client->post(
            "clinical/medication-adverse-events/{$eventId}/response",
            array_filter(['clinical_response' => $clinicalResponse, 'outcome' => $outcome], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function escalate(ClinicalActor $actor, string $eventId): array
    {
        return $this->client->post(
            "clinical/medication-adverse-events/{$eventId}/escalate",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function close(ClinicalActor $actor, string $eventId, string $outcome): array
    {
        return $this->client->post(
            "clinical/medication-adverse-events/{$eventId}/close",
            ['outcome' => $outcome],
            ['business_id' => $actor->businessId],
        );
    }
}
