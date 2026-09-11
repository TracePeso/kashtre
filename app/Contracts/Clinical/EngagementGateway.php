<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\ClinicalActor;

/**
 * Patient messaging — v6.1 Volume 13. Only SendPatientMessage is wired on
 * Clinical's side; see EngagementController's docblock for the rest
 * (real telehealth/remote-device vendor integrations, none configured).
 */
interface EngagementGateway
{
    /** @return array<int, array<string, mixed>> */
    public function messages(ClinicalActor $actor): array;

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(ClinicalActor $actor, string $patientId, string $body, bool $isUrgent = false, ?string $threadId = null): array;
}
