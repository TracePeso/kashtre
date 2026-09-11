<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\AiUseCaseGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiAiUseCaseGateway implements AiUseCaseGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function list(ClinicalActor $actor): array
    {
        $data = $this->client->get('clinical/ai-use-cases', [], ['business_id' => $actor->businessId]);

        return array_values(array_filter((array) $data, 'is_array'));
    }

    public function register(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/ai-use-cases', $payload, [
            'business_id' => $actor->businessId,
            'idempotency_key' => 'ai-use-case-register-'.($payload['code'] ?? ''),
        ]);
    }

    public function setStatus(ClinicalActor $actor, string $code, string $status, ?string $riskLevel = null): array
    {
        return $this->client->patch("clinical/ai-use-cases/{$code}", array_filter([
            'status' => $status,
            'risk_level' => $riskLevel,
        ]), ['business_id' => $actor->businessId]);
    }
}
