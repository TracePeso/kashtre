<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\InteroperabilityGateway;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: no local equivalent for this Clinical-owned,
 * package-based data — returns empty rather than throwing, since this
 * gateway is read-only and an empty list is a truthful answer for a driver
 * that has none of these records.
 */
class LocalInteroperabilityGateway implements InteroperabilityGateway
{
    public function exchangeJobs(ClinicalActor $actor): array
    {
        return [];
    }

    public function measureVersions(ClinicalActor $actor): array
    {
        return [];
    }

    public function measureRuns(ClinicalActor $actor): array
    {
        return [];
    }
}
