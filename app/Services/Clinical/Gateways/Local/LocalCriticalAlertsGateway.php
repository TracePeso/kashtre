<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\CriticalAlertsGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: no diagnostic/lab critical-value pipeline exists
 * against this schema (lab/imaging are out of scope for the local tables) —
 * an empty feed is the honest answer, not a fabricated one.
 */
class LocalCriticalAlertsGateway implements CriticalAlertsGateway
{
    public function feed(
        ClinicalActor $actor,
        string $scope = self::SCOPE_MY_PATIENTS,
        ?string $wardCode = null,
        ?string $severityTier = null,
        bool $includeAcknowledged = false,
        int $limit = 100,
    ): array {
        return ['alerts' => [], 'count' => 0, 'by_severity' => [], 'oldest_unacknowledged_at' => null];
    }

    public function acknowledge(ClinicalActor $actor, int|string $alertId): void
    {
        throw new RuntimeException('Critical alerts are not available under the local clinical driver.');
    }

    public function review(ClinicalActor $actor, int|string $alertId, string $reviewNotes): array
    {
        throw new RuntimeException('Critical alerts are not available under the local clinical driver.');
    }

    public function action(ClinicalActor $actor, int|string $alertId, string $actionTaken): array
    {
        throw new RuntimeException('Critical alerts are not available under the local clinical driver.');
    }

    public function close(ClinicalActor $actor, int|string $alertId, string $closureReason): array
    {
        throw new RuntimeException('Critical alerts are not available under the local clinical driver.');
    }
}
