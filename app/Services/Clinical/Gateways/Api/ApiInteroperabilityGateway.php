<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\InteroperabilityGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiInteroperabilityGateway implements InteroperabilityGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function exchangeJobs(ClinicalActor $actor): array
    {
        return $this->rows($actor, 'clinical/exchange-jobs');
    }

    public function measureVersions(ClinicalActor $actor): array
    {
        return $this->rows($actor, 'clinical/quality-measures');
    }

    public function measureRuns(ClinicalActor $actor): array
    {
        return $this->rows($actor, 'clinical/quality-measures/runs');
    }

    private function rows(ClinicalActor $actor, string $path): array
    {
        $data = $this->client->get($path, [], ['business_id' => $actor->businessId]);

        return array_values(array_filter((array) $data, 'is_array'));
    }
}
