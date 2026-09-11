<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\PatientWorkspaceGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: the patient workspace projection (banner,
 * timeline, patient lists — SRD v6.1 Phase 2) is Clinical-owned, with no
 * local equivalent; it merges data from several Clinical-side tables this
 * host's local driver never had.
 */
class LocalPatientWorkspaceGateway implements PatientWorkspaceGateway
{
    public function banner(ClinicalActor $actor, string $patientId, ?string $visitId = null, ?int $viewingUserId = null): array
    {
        $this->refuse();
    }

    public function timeline(ClinicalActor $actor, string $patientId, ?string $visitId = null, int $limit = 100): array
    {
        $this->refuse();
    }

    public function patientList(ClinicalActor $actor, string $type, int $userId, array $roleCodes = []): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('The patient workspace projection (v6.1 Phase 2) is only available under CLINICAL_DRIVER=api.');
    }
}
