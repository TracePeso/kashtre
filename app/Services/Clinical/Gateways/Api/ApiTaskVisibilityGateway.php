<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\TaskVisibilityGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Log;

/**
 * CLINICAL_DRIVER=api: `GET clinical/tasks/visibility` (confirmed live
 * 2026-08-19). Fails soft to an empty, clearly-shaped result.
 */
class ApiTaskVisibilityGateway implements TaskVisibilityGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function filtered(ClinicalActor $actor, string $scope, array $filters = []): array
    {
        try {
            return $this->client->get('clinical/tasks/visibility', array_filter(array_merge($filters, [
                'scope' => $scope,
                'user_id' => $actor->userId,
            ]), fn ($v) => $v !== null && $v !== ''), ['business_id' => $actor->businessId]);
        } catch (ClinicalApiException $e) {
            Log::warning('Could not load the task visibility board.', $e->context());

            return [
                'scope' => $scope, 'patients' => [], 'work_orders' => [],
                'outstanding_observations' => [], 'unacknowledged_alerts' => [], 'enterprise_queues' => [],
                'summary' => [],
            ];
        }
    }
}
