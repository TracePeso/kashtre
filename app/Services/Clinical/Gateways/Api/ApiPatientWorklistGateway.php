<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\PatientWorklistGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\PatientTask;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: the clinician's worklist from the Clinical Module,
 * which already joins care assignments to bed occupancy, open tasks and
 * unacknowledged alerts and returns them pre-sorted.
 */
class ApiPatientWorklistGateway implements PatientWorklistGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function myPatients(ClinicalActor $actor): array
    {
        try {
            $data = $this->client->get(
                'clinical/tasks/my-patients',
                ['user_id' => $actor->userId],
                ['business_id' => $actor->businessId],
            );
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the clinician worklist.', $e->context());

            return [];
        }

        $rows = array_is_list($data) ? $data : ($data['items'] ?? []);

        return array_map(
            fn (array $row) => PatientTask::fromApi($row),
            array_values(array_filter($rows, 'is_array')),
        );
    }
}
