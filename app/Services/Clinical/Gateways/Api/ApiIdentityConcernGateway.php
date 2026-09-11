<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\IdentityConcernGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiIdentityConcernGateway implements IdentityConcernGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function report(ClinicalActor $actor, string $patientId, array $payload): array
    {
        return $this->client->post(
            "clinical/patients/{$patientId}/identity-concerns",
            [
                'concern_type' => $payload['concern_type'],
                'description' => $payload['description'],
                'reported_by_user_id' => $payload['reported_by_user_id'],
            ],
            ['business_id' => $actor->businessId],
        );
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/identity-concerns",
            [],
            ['business_id' => $actor->businessId],
        );

        return $this->rows($data);
    }

    public function open(ClinicalActor $actor): array
    {
        $data = $this->client->get(
            'clinical/identity-concerns',
            ['status' => 'OPEN'],
            ['business_id' => $actor->businessId],
        );

        return $this->rows($data);
    }

    public function resolve(ClinicalActor $actor, string $concernId, string $resolution): array
    {
        return $this->client->post(
            "clinical/identity-concerns/{$concernId}/resolve",
            ['resolution' => $resolution],
            ['business_id' => $actor->businessId],
        );
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $payload): array
    {
        $rows = array_is_list($payload) ? $payload : ($payload['items'] ?? $payload['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }
}
