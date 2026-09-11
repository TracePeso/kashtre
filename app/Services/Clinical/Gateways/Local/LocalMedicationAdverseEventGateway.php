<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\MedicationAdverseEventGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: adverse-event reporting (SRD v6.1 Phase 6) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalMedicationAdverseEventGateway implements MedicationAdverseEventGateway
{
    public function report(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array
    {
        $this->refuse();
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $this->refuse();
    }

    public function recordResponse(ClinicalActor $actor, string $eventId, string $clinicalResponse, ?string $outcome = null): array
    {
        $this->refuse();
    }

    public function escalate(ClinicalActor $actor, string $eventId): array
    {
        $this->refuse();
    }

    public function close(ClinicalActor $actor, string $eventId, string $outcome): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Medication adverse-event reporting (v6.1 Phase 6) is only available under CLINICAL_DRIVER=api.');
    }
}
