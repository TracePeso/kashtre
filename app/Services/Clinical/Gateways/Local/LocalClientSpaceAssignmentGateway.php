<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ClientSpaceAssignmentGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: client-space assignment governance (SRD v6.1
 * Phase 1 §4) is Clinical-owned, with no local table equivalent.
 */
class LocalClientSpaceAssignmentGateway implements ClientSpaceAssignmentGateway
{
    public function forUser(ClinicalActor $actor, int $userId): array
    {
        $this->refuse();
    }

    public function assign(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function end(ClinicalActor $actor, string $assignmentId, ?string $reason = null): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Client-space assignments (v6.1 Phase 1) are only available under CLINICAL_DRIVER=api.');
    }
}
