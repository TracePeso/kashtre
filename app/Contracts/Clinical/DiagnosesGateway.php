<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\ClinicalDiagnosis;

/**
 * The patient's problem list — SRD Clinical Diagnoses.
 *
 * Note the drivers disagree on what a diagnosis minimally is. Main's local
 * table accepts free text with an optional ICD-11 code; Clinical requires
 * `icd11_code`, `display_label` and `visit_id`. The stricter side wins on the
 * API driver, and record() surfaces that refusal rather than silently dropping
 * a diagnosis a clinician believed they had saved.
 */
interface DiagnosesGateway
{
    /**
     * @return array<int, ClinicalDiagnosis>
     */
    public function forPatient(ClinicalActor $actor, string $patientId): array;

    public function record(
        ClinicalActor $actor,
        string $patientId,
        string $description,
        ?string $icd11Code = null,
        ?string $visitId = null,
    ): void;

    /** Whether this driver can accept a diagnosis without an ICD-11 code. */
    public function allowsUncodedDiagnosis(): bool;
}
