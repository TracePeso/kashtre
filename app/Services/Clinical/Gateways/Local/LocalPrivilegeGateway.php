<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\PrivilegeGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: high-risk privilege governance (SRD v6.1
 * Phase 1 §9) is Clinical-owned, with no local table equivalent.
 */
class LocalPrivilegeGateway implements PrivilegeGateway
{
    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $this->refuse();
    }

    public function grant(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function suspend(ClinicalActor $actor, string $privilegeId, string $reason): array
    {
        $this->refuse();
    }

    public function reinstate(ClinicalActor $actor, string $privilegeId): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Privilege governance (v6.1 Phase 1) is only available under CLINICAL_DRIVER=api.');
    }
}
