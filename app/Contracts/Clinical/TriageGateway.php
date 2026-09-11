<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * OPD/ward triage acuity — SRD §4.1.1. Vitals are captured through the
 * ordinary observation endpoint first (CaptureObservations); this scores
 * whatever is already on the chart and, when $announce is true, pushes the
 * resulting priority colour to the universal queue. Confirmed live against
 * `POST clinical/patients/{id}/triage` 2026-08-18.
 *
 * No local equivalent — NEWS2/SATS scoring is Clinical's calculated-
 * indicator engine (SRD §12.1), not something this schema computes.
 * LocalTriageGateway refuses rather than faking a score.
 */
interface TriageGateway
{
    /**
     * @return array{
     *     priority: array{score_code: ?string, colour: ?string, label: ?string, total: ?int},
     *     scores: array<string, array<string, mixed>>,
     *     inputs_used: array<string, mixed>,
     *     announced: bool,
     * }
     */
    public function assess(ClinicalActor $actor, string $patientId, string $visitId, bool $announce = true): array;
}
