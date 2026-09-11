<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * §13 FHIR interface — read-only R4 export. `FhirExportService` (Main's
 * pre-existing implementation) was never driver-aware: it always built its
 * bundle from Main's own local `clinical_*` tables, which under
 * CLINICAL_DRIVER=api are not where the chart actually lives — an export
 * taken today would be built from stale/empty local rows while the real
 * chart sits in Clinical. This gateway is the fix: the API driver calls
 * Clinical's own `$everything` operation instead of exporting nothing.
 */
interface FhirExportGateway
{
    /**
     * @return array<string, mixed> a FHIR Bundle (raw, not the {data,meta} envelope)
     */
    public function patientEverything(ClinicalActor $actor, string $patientId): array;
}
