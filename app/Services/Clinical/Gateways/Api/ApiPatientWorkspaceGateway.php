<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\PatientWorkspaceGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiPatientWorkspaceGateway implements PatientWorkspaceGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function banner(ClinicalActor $actor, string $patientId, ?string $visitId = null, ?int $viewingUserId = null): array
    {
        return $this->client->get(
            "clinical/patients/{$patientId}/banner",
            array_filter(['visit_id' => $visitId, 'viewing_user_id' => $viewingUserId], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function timeline(ClinicalActor $actor, string $patientId, ?string $visitId = null, int $limit = 100): array
    {
        $data = $this->client->get(
            "clinical/patients/{$patientId}/timeline",
            array_filter(['visit_id' => $visitId, 'limit' => $limit], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function patientList(ClinicalActor $actor, string $type, int $userId, array $roleCodes = []): array
    {
        $data = $this->client->get(
            'clinical/patient-lists',
            array_filter([
                'type' => $type,
                'user_id' => $userId,
                'role_codes' => $roleCodes !== [] ? $roleCodes : null,
            ], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_values(array_filter($rows, 'is_array'));
    }
}
