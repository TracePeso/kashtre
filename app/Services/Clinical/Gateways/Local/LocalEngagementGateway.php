<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\EngagementGateway;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: patient messaging (v6.1 Volume 13) is
 * Clinical-owned, with no local table equivalent.
 */
class LocalEngagementGateway implements EngagementGateway
{
    public function messages(ClinicalActor $actor): array
    {
        return [];
    }

    public function sendMessage(ClinicalActor $actor, string $patientId, string $body, bool $isUrgent = false, ?string $threadId = null): array
    {
        throw new RuntimeException('Patient messaging (v6.1 Volume 13) is only available under CLINICAL_DRIVER=api.');
    }
}
