<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * §10.5's four localised views — a different question each, not variations
 * on one: MY_PATIENTS is "who am I responsible for", MY_WARD is "who is on
 * this ward right now", MY_TEAM is "who does my care team own", and
 * MY_CURRENT_WORK is "what must I personally do" — including on patients
 * someone else owns (the nurse tasked with a dressing on another
 * clinician's patient). None of these were reachable before — Main's
 * worklist board used the simpler `tasks/my-patients` (location only) and
 * never exposed scope switching or the location/clinical filters at all.
 *
 * A raw array, not a DTO — the per-scope shape genuinely differs (a
 * patient list for MY_PATIENTS/MY_WARD/MY_TEAM, work_orders/observations/
 * alerts/queues for MY_CURRENT_WORK) and is display-only.
 */
interface TaskVisibilityGateway
{
    public const SCOPE_MY_PATIENTS = 'MY_PATIENTS';

    public const SCOPE_MY_WARD = 'MY_WARD';

    public const SCOPE_MY_TEAM = 'MY_TEAM';

    public const SCOPE_MY_CURRENT_WORK = 'MY_CURRENT_WORK';

    /**
     * @param  array<string, mixed>  $filters  space_id, building_wing, ward_code, room_number, bed_code, team_id, specialty, pathway_code
     * @return array<string, mixed>
     */
    public function filtered(ClinicalActor $actor, string $scope, array $filters = []): array;
}
