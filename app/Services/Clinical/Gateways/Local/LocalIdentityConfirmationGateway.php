<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\IdentityConfirmationGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: positive patient identification (SRD v6.1
 * Phase 2) is Clinical-owned, with no local table equivalent.
 */
class LocalIdentityConfirmationGateway implements IdentityConfirmationGateway
{
    public function confirm(ClinicalActor $actor, string $patientId, array $payload): array
    {
        $this->refuse();
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Identity confirmations (v6.1 Phase 2) are only available under CLINICAL_DRIVER=api.');
    }
}
