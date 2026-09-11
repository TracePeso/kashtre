<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\PatientTask;

/**
 * SRD §3.2 "My Patients" — the clinician's own worklist, which is what makes
 * an enterprise-wide queue usable by one person.
 *
 * Ordering is the gateway's responsibility, not the view's: patients needing
 * attention come first, because a board that lists them alphabetically buries
 * the one with an unacknowledged critical alert.
 */
interface PatientWorklistGateway
{
    /**
     * @return array<int, PatientTask>
     */
    public function myPatients(ClinicalActor $actor): array;
}
