<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\HandoverGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\ClinicalRequestContext;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `GET clinical/handover` (confirmed live 2026-08-18).
 * Fails soft to an empty rollup — a shift handover that cannot be compiled
 * should say so, not take down the page it's embedded on.
 */
class ApiHandoverGateway implements HandoverGateway
{
    public function __construct(
        private readonly ClinicalApiClient $client,
        private readonly ClinicalRequestContext $context,
    ) {
    }

    public function compile(ClinicalActor $actor, string $scope, ?string $wardCode = null): array
    {
        try {
            $response = $this->client->getEnvelope('clinical/handover', array_filter([
                'scope' => $scope,
                'user_id' => $actor->userId,
                'ward_code' => $wardCode,
                'role_codes' => $this->context->rolesFor(),
            ], fn ($value) => $value !== null && $value !== []), ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not compile the shift handover.', $e->context());

            return ['patients' => [], 'scope' => $scope, 'ward_code' => $wardCode, 'generated_at' => null, 'summary' => []];
        }

        $meta = $response['meta'] ?? [];

        return [
            'patients' => $response['data'] ?? [],
            'scope' => $meta['scope'] ?? $scope,
            'ward_code' => $meta['ward_code'] ?? $wardCode,
            'generated_at' => $meta['generated_at'] ?? null,
            'summary' => $meta['summary'] ?? [],
        ];
    }
}
