<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\EncounterGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: the encounter-workspace record (SRD v6.1
 * Phase 2) is Clinical-owned, with no local table equivalent — Main's own
 * visit_id concept is unaffected and unchanged either way.
 */
class LocalEncounterGateway implements EncounterGateway
{
    public function create(ClinicalActor $actor, array $payload): array
    {
        $this->refuse();
    }

    public function forPatient(ClinicalActor $actor, string $patientId): array
    {
        $this->refuse();
    }

    public function show(ClinicalActor $actor, string $encounterId): array
    {
        $this->refuse();
    }

    public function transition(ClinicalActor $actor, string $encounterId, string $status, ?string $reason = null): array
    {
        $this->refuse();
    }

    public function closureChecks(ClinicalActor $actor, string $encounterId): array
    {
        $this->refuse();
    }

    public function close(ClinicalActor $actor, string $encounterId, bool $override = false): array
    {
        $this->refuse();
    }

    public function reopen(ClinicalActor $actor, string $encounterId, string $reason): array
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new RuntimeException('The encounter workspace (v6.1 Phase 2) is only available under CLINICAL_DRIVER=api.');
    }
}
