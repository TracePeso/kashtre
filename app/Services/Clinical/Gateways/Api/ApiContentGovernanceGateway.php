<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\ContentGovernanceGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiContentGovernanceGateway implements ContentGovernanceGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function list(ClinicalActor $actor, ?string $contentType = null): array
    {
        $data = $this->client->get(
            'clinical/content-versions',
            array_filter(['content_type' => $contentType]),
            ['business_id' => $actor->businessId],
        );

        return array_values(array_filter((array) $data, 'is_array'));
    }

    public function show(ClinicalActor $actor, string $versionId): array
    {
        return $this->client->get("clinical/content-versions/{$versionId}", [], ['business_id' => $actor->businessId]);
    }

    public function createVersion(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/content-versions', $payload, [
            'business_id' => $actor->businessId,
            'idempotency_key' => 'content-version-'.md5(json_encode($payload)),
        ]);
    }

    public function validateVersion(ClinicalActor $actor, string $versionId): array
    {
        return $this->client->post(
            "clinical/content-versions/{$versionId}/validate",
            [],
            ['business_id' => $actor->businessId, 'idempotency_key' => "content-version-{$versionId}-validate-".now()->format('YmdHis')],
        );
    }
}
