<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\MedicationReconciliationGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: medication reconciliation (SRD v6.1 Phase 6) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalMedicationReconciliationGateway implements MedicationReconciliationGateway
{
    public function start(ClinicalActor $actor, string $patientId, ?string $visitId, array $payload): array
    {
        $this->refuse();
    }

    public function show(ClinicalActor $actor, string $reconciliationId): array
    {
        $this->refuse();
    }

    public function addItem(ClinicalActor $actor, string $reconciliationId, array $item): array
    {
        $this->refuse();
    }

    public function decideItem(ClinicalActor $actor, string $itemId, string $decision, int $decidedByUserId): array
    {
        $this->refuse();
    }

    public function complete(ClinicalActor $actor, string $reconciliationId): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('Medication reconciliation (v6.1 Phase 6) is only available under CLINICAL_DRIVER=api.');
    }
}
