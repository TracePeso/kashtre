<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\PermissionCatalogGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiPermissionCatalogGateway implements PermissionCatalogGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function list(ClinicalActor $actor, array $filters = []): array
    {
        $response = $this->client->getEnvelope('settings/permission-catalog', array_filter([
            'resource_family' => $filters['resource_family'] ?? null,
            'risk_tier' => $filters['risk_tier'] ?? null,
            'search' => $filters['search'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);

        return [
            'data' => $response['data'] ?? [],
            'meta' => $response['meta'] ?? [],
        ];
    }

    public function register(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('settings/permission-catalog', array_filter([
            'code' => $payload['code'],
            'description' => $payload['description'],
            'risk_tier' => $payload['risk_tier'],
            'default_scope' => $payload['default_scope'] ?? null,
            'requires_credential' => $payload['requires_credential'] ?? null,
            'break_glass_eligible' => $payload['break_glass_eligible'] ?? null,
            'audit_level' => $payload['audit_level'] ?? null,
        ], fn ($v) => $v !== null), [
            'business_id' => $actor->businessId,
            'idempotency_key' => 'perm-catalog-register-'.($payload['code'] ?? ''),
        ]);
    }

    public function deactivate(ClinicalActor $actor, string $permissionId): void
    {
        $this->client->post(
            "settings/permission-catalog/{$permissionId}/deactivate",
            [],
            ['business_id' => $actor->businessId],
        );
    }
}
