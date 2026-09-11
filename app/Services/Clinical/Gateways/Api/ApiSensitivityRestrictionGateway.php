<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiSensitivityRestrictionGateway implements SensitivityRestrictionGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get(
            'clinical/sensitivity-restrictions',
            ['patient_id' => $patientId],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function restrict(ClinicalActor $actor, string $patientId, array $payload): array
    {
        return $this->client->post('clinical/sensitivity-restrictions', array_filter([
            'patient_id' => $patientId,
            'level' => $payload['level'],
            'label' => $payload['label'],
            'reason' => $payload['reason'],
            'resource_reference' => $payload['resource_reference'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function lift(ClinicalActor $actor, string $restrictionId, string $reason): array
    {
        return $this->client->post(
            "clinical/sensitivity-restrictions/{$restrictionId}/lift",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }
}
