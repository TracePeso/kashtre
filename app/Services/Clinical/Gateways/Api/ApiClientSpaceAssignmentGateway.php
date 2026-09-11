<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ClientSpaceAssignmentGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiClientSpaceAssignmentGateway implements ClientSpaceAssignmentGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $data = $this->client->get(
            'clinical/client-space-assignments',
            ['user_id' => $userId],
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function assign(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/client-space-assignments', array_filter([
            'user_id' => $payload['user_id'],
            'client_space_id' => $payload['client_space_id'],
            'assignment_type' => $payload['assignment_type'],
            'effective_start' => $payload['effective_start'],
            'effective_end' => $payload['effective_end'] ?? null,
            'permitted_operational_function' => $payload['permitted_operational_function'] ?? null,
            'approving_authority' => $payload['approving_authority'] ?? null,
            'source_reference' => $payload['source_reference'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function end(ClinicalActor $actor, string $assignmentId, ?string $reason = null): array
    {
        return $this->client->post(
            "clinical/client-space-assignments/{$assignmentId}/end",
            array_filter(['reason' => $reason], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }
}
