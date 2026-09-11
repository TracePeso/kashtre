<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\EncounterSectionGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no equivalent table exists — MDT co-signing has
 * no local implementation to fall back to.
 */
class LocalEncounterSectionGateway implements EncounterSectionGateway
{
    public function forPatient(ClinicalActor $actor, string $patientId, ?string $visitId = null, bool $includeWithdrawn = false): array
    {
        return [];
    }

    public function sign(
        ClinicalActor $actor,
        string $patientId,
        string $visitId,
        string $sectionCode,
        string $sectionName,
        ?string $attestationNote = null,
    ): array {
        throw new RuntimeException('Encounter-section co-signing is not available under the local clinical driver.');
    }

    public function withdraw(ClinicalActor $actor, string $patientId, int|string $signatureId, string $reason): void
    {
        throw new RuntimeException('Encounter-section co-signing is not available under the local clinical driver.');
    }
}
