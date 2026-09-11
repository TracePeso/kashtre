<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\FhirExportGateway;
use App\Services\Clinical\FhirExportService;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: the pre-existing FhirExportService, which builds
 * the bundle from Main's own local clinical_* tables — correct under this
 * driver, since that is where the chart actually lives.
 */
class LocalFhirExportGateway implements FhirExportGateway
{
    public function __construct(private readonly FhirExportService $service)
    {
    }

    public function patientEverything(ClinicalActor $actor, string $patientId): array
    {
        return $this->service->exportPatientBundle($actor->businessId, $patientId);
    }
}
