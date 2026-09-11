<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\PrivilegeGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiPrivilegeGateway implements PrivilegeGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $data = $this->client->get(
            'clinical/privileges',
            ['user_id' => $userId],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function grant(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/privileges', array_filter([
            'user_id' => $payload['user_id'],
            'category' => $payload['category'],
            'effective_start' => $payload['effective_start'],
            'effective_end' => $payload['effective_end'] ?? null,
            'credential_reference' => $payload['credential_reference'] ?? null,
            'granted_by' => $payload['granted_by'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function suspend(ClinicalActor $actor, string $privilegeId, string $reason): array
    {
        return $this->client->post(
            "clinical/privileges/{$privilegeId}/suspend",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }

    public function reinstate(ClinicalActor $actor, string $privilegeId): array
    {
        return $this->client->post(
            "clinical/privileges/{$privilegeId}/reinstate",
            [],
            ['business_id' => $actor->businessId],
        );
    }
}
