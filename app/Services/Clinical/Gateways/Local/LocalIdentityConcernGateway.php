<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\IdentityConcernGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: identity-concern reporting (SRD v6.1 Phase 2) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalIdentityConcernGateway implements IdentityConcernGateway
{
    public function report(ClinicalActor $actor, string $patientId, array $payload): array
    {
        $this->refuse();
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $this->refuse();
    }

    public function open(ClinicalActor $actor): array
    {
        $this->refuse();
    }

    public function resolve(ClinicalActor $actor, string $concernId, string $resolution): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Identity-concern reporting (v6.1 Phase 2) is only available under CLINICAL_DRIVER=api.');
    }
}
