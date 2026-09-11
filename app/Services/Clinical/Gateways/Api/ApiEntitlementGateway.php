<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\EntitlementGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\EntitlementBalance;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: entitlement balances over HTTP — API Integration
 * Guide / SRD v6.0 §6.3.
 */
class ApiEntitlementGateway implements EntitlementGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function balancesFor(ClinicalActor $actor, string $patientId): array
    {
        try {
            $data = $this->client->get(
                "clinical/patients/{$patientId}/entitlements",
                [],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load entitlement balances.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? $data['data'] ?? []);

        return array_map(
            fn (array $row) => EntitlementBalance::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );
    }
}
