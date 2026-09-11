<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiIdentityConfirmationGateway implements IdentityConfirmationGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function confirm(ClinicalActor $actor, string $patientId, array $payload): array
    {
        return $this->client->post(
            "clinical/patients/{$patientId}/identity-confirmations",
            array_filter([
                'action_type' => $payload['action_type'],
                'confirmed_by_user_id' => $payload['confirmed_by_user_id'],
                'method' => $payload['method'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/identity-confirmations",
            [],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }
}
