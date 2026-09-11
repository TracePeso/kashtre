<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\DelegationGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiDelegationGateway implements DelegationGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $data = $this->client->get(
            'clinical/delegations',
            ['user_id' => $userId],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function delegate(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/delegations', array_filter([
            'delegator_user_id' => $payload['delegator_user_id'],
            'delegate_user_id' => $payload['delegate_user_id'],
            'permission_bundle' => $payload['permission_bundle'],
            'scope' => $payload['scope'],
            'start' => $payload['start'],
            'end' => $payload['end'],
            'reason' => $payload['reason'],
            'client_space_id' => $payload['client_space_id'] ?? null,
            'patient_id' => $payload['patient_id'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function revoke(ClinicalActor $actor, string $delegationId, string $reason): array
    {
        return $this->client->post(
            "clinical/delegations/{$delegationId}/revoke",
            ['reason' => $reason],
            ['business_id' => $actor->businessId],
        );
    }
}
