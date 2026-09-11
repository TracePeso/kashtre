<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\DelegationGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: delegation/cross-cover (SRD v6.1 Phase 1 §10) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalDelegationGateway implements DelegationGateway
{
    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $this->refuse();
    }

    public function delegate(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function revoke(ClinicalActor $actor, string $delegationId, string $reason): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Delegation (v6.1 Phase 1) is only available under CLINICAL_DRIVER=api.');
    }
}
