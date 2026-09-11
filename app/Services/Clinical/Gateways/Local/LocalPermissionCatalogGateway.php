<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\PermissionCatalogGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: the published atomic-permission catalogue
 * (SRD v6.1 Phase 1 §6) is Clinical-owned, with no local table equivalent.
 */
class LocalPermissionCatalogGateway implements PermissionCatalogGateway
{
    public function list(ClinicalActor $actor, array $filters = []): array
    {
        $this->refuse();
    }

    public function register(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function deactivate(ClinicalActor $actor, string $permissionId): void
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('The permission catalogue (v6.1 Phase 1) is only available under CLINICAL_DRIVER=api.');
    }
}
