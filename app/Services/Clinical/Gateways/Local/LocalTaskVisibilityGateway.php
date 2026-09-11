<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\TaskVisibilityGateway;
use App\Services\Clinical\CareRelationshipChecker;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: only MY_PATIENTS has a real local answer
 * (CareRelationshipChecker's own worklist, same source
 * MyPatientTasksBoard already uses). MY_WARD/MY_TEAM/MY_CURRENT_WORK and
 * the location/clinical filters have no equivalent against this schema —
 * an honestly limited board, not a fabricated one.
 */
class LocalTaskVisibilityGateway implements TaskVisibilityGateway
{
    public function __construct(private readonly CareRelationshipChecker $careChecker)
    {
    }

    public function filtered(ClinicalActor $actor, string $scope, array $filters = []): array
    {
        if ($scope !== self::SCOPE_MY_PATIENTS) {
            return [
                'scope' => $scope, 'patients' => [], 'work_orders' => [],
                'outstanding_observations' => [], 'unacknowledged_alerts' => [], 'enterprise_queues' => [],
                'summary' => [], 'filters' => $filters,
            ];
        }

        $patientIds = $this->careChecker->myPatientClientIds($actor->userId, $actor->businessId);

        return [
            'scope' => $scope,
            'patients' => array_map(fn ($id) => ['patient_id' => $id], $patientIds),
            'work_orders' => [],
            'outstanding_observations' => [],
            'unacknowledged_alerts' => [],
            'enterprise_queues' => [],
            'summary' => ['patient_count' => count($patientIds)],
            'filters' => $filters,
        ];
    }
}
