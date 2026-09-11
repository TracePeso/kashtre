<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * v6.1 Volume 12 — read-only. CreateExchangeJob/RunQualityMeasure are not
 * exposed on Clinical's side; see InteroperabilityController's docblock for
 * why (an unbound policy-decision contract, and a caller-supplied clinical
 * quality-measure evaluator neither this host nor Main should fabricate).
 */
interface InteroperabilityGateway
{
    /** @return array<int, array<string, mixed>> */
    public function exchangeJobs(ClinicalActor $actor): array;

    /** @return array<int, array<string, mixed>> */
    public function measureVersions(ClinicalActor $actor): array;

    /** @return array<int, array<string, mixed>> */
    public function measureRuns(ClinicalActor $actor): array;
}
