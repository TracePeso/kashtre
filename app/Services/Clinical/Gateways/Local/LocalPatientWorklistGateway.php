<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\PatientWorklistGateway;
use App\Models\ClinicalBed;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\PatientTask;

/**
 * CLINICAL_DRIVER=local: the worklist assembled from the local care
 * assignments and clinical_beds.
 *
 * Open tasks and unacknowledged alerts are not modelled locally, so they come
 * back as zero. That is a real difference between the drivers rather than a
 * placeholder: on this driver the board can say where a patient is, but not
 * whether they need attention, so it cannot sort by urgency either.
 */
class LocalPatientWorklistGateway implements PatientWorklistGateway
{
    public function __construct(private readonly CareAccessGateway $careAccess)
    {
    }

    public function myPatients(ClinicalActor $actor): array
    {
        $patientIds = $this->careAccess->myPatientIds($actor);

        if ($patientIds === []) {
            return [];
        }

        $beds = ClinicalBed::query()
            ->whereIn('current_client_id', $patientIds)
            ->where('operational_state', ClinicalBed::STATE_OCCUPIED)
            ->with('ward')
            ->get()
            ->keyBy('current_client_id');

        $tasks = [];

        foreach ($patientIds as $patientId) {
            $bed = $beds->get($patientId);

            $tasks[] = new PatientTask(
                patient_id: (string) $patientId,
                visit_id: $bed?->current_visit_id,
                bed_code: $bed?->bed_code,
                ward_code: $bed?->ward?->ward_code,
                ward_name: $bed?->ward?->ward_name,
                room_number: null,
                building_wing: $bed?->ward?->building_wing,
                is_overflow_bed: (bool) ($bed?->is_overflow ?? false),
                is_admitted: $bed !== null,
            );
        }

        return $tasks;
    }
}
