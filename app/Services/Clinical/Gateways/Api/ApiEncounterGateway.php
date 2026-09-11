<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\EncounterGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiEncounterGateway implements EncounterGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function create(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/encounters', array_filter([
            'patient_id' => $payload['patient_id'],
            'visit_id' => $payload['visit_id'],
            'encounter_class' => $payload['encounter_class'],
            'service' => $payload['service'] ?? null,
            'facility_id' => $payload['facility_id'] ?? null,
            'responsible_clinician_id' => $payload['responsible_clinician_id'] ?? null,
            'initial_client_space_id' => $payload['initial_client_space_id'] ?? null,
        ], fn ($v) => $v !== null), [
            'business_id' => $actor->businessId,
            'idempotency_key' => 'encounter-create-'.$payload['patient_id'].'-'.$payload['visit_id'],
        ]);
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/encounters",
            [],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function show(ClinicalActor $actor, string $encounterId): array
    {
        return $this->client->get(
            "clinical/encounters/{$encounterId}",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function transition(ClinicalActor $actor, string $encounterId, string $status, ?string $reason = null): array
    {
        return $this->client->post(
            "clinical/encounters/{$encounterId}/status",
            array_filter(['status' => $status, 'reason' => $reason], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function closureChecks(ClinicalActor $actor, string $encounterId): array
    {
        $data = $this->client->get(
            "clinical/encounters/{$encounterId}/closure-checks",
            [],
            ['business_id' => $actor->businessId],
        );

        return [
            'ready' => (bool) ($data['ready'] ?? false),
            'items' => (array) ($data['items'] ?? []),
        ];
    }

    public function close(ClinicalActor $actor, string $encounterId, bool $override = false): array
    {
        return $this->client->post(
            "clinical/encounters/{$encounterId}/close",
            ['override' => $override],
            ['business_id' => $actor->businessId],
        );
    }

    public function reopen(ClinicalActor $actor, string $encounterId, string $reason): array
    {
        return $this->client->post(
            "clinical/encounters/{$encounterId}/reopen",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }
}
