<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * End-of-shift handover — SRD §2.2 "Handover Task Routing": every patient
 * the clinician (or their team, or their ward) owns, sickest first, with
 * what is outstanding on each. Confirmed live against `GET clinical/
 * handover` 2026-08-18.
 *
 * A raw array, not a DTO — the per-patient block (location, ownership,
 * critical_alerts, open_tasks, observations, medications) is Clinical's own
 * already-well-structured compiled output, display-only, and a DTO here
 * would only mean chasing field-name drift for no behavioural gain (same
 * reasoning as CareAccessGateway::teamFor()).
 */
interface HandoverGateway
{
    public const SCOPE_MY_PATIENTS = 'MY_PATIENTS';

    public const SCOPE_MY_TEAM = 'MY_TEAM';

    public const SCOPE_MY_WARD = 'MY_WARD';

    /**
     * @return array{patients: array<int, array<string, mixed>>, scope: string, ward_code: ?string, generated_at: ?string, summary: array<string, mixed>}
     */
    public function compile(ClinicalActor $actor, string $scope, ?string $wardCode = null): array;
}
