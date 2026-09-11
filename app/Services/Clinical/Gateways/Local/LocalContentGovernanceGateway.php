<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\ContentGovernanceGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: Content Governance (v6.1 Volume 14) is
 * Clinical-owned, package-based data with no local table equivalent.
 */
class LocalContentGovernanceGateway implements ContentGovernanceGateway
{
    public function list(ClinicalActor $actor, ?string $contentType = null): array
    {
        $this->refuse();
    }

    public function show(ClinicalActor $actor, string $versionId): array
    {
        $this->refuse();
    }

    public function createVersion(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function validateVersion(ClinicalActor $actor, string $versionId): array
    {
        $this->refuse();
    }

    private function refuse(): void
    {
        throw new RuntimeException('Content Governance (v6.1 Volume 14) is only available under CLINICAL_DRIVER=api.');
    }
}
