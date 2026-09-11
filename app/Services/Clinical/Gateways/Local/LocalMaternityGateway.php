<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\MaternityGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no maternity/birth-event schema exists locally —
 * this was never part of Main's original clinical_* tables.
 */
class LocalMaternityGateway implements MaternityGateway
{
    public function options(ClinicalActor $actor): array
    {
        return ['DELIVERY_MODE' => [], 'PRESENTATION' => [], 'MATERNAL_OUTCOME' => []];
    }

    public function forPatient(ClinicalActor $actor, string $motherPatientId): array
    {
        return [];
    }

    public function recordBirth(
        ClinicalActor $actor,
        string $motherPatientId,
        string $motherVisitId,
        string $deliveryAt,
        string $deliveryModeCode,
        array $infants,
        ?float $gestationWeeks = null,
        ?string $presentationCode = null,
        ?string $maternalOutcomeCode = null,
        ?string $deliveryNotes = null,
    ): array {
        throw new RuntimeException('The maternity portal is not available under the local clinical driver.');
    }

    public function recordApgar(ClinicalActor $actor, int|string $birthRecordId, int $timepointMinutes, array $components): array
    {
        throw new RuntimeException('The maternity portal is not available under the local clinical driver.');
    }
}
