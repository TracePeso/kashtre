<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\SensitivityRestrictionGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: sensitivity restrictions (SRD v6.1 Phase 1 §12)
 * are Clinical-owned, with no local table equivalent.
 */
class LocalSensitivityRestrictionGateway implements SensitivityRestrictionGateway
{
    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $this->refuse();
    }

    public function restrict(ClinicalActor $actor, string $patientId, array $payload): array
    {
        $this->refuse();
    }

    public function lift(ClinicalActor $actor, string $restrictionId, string $reason): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Sensitivity restrictions (v6.1 Phase 1) are only available under CLINICAL_DRIVER=api.');
    }
}
