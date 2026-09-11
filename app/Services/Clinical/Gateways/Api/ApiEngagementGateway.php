<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\EngagementGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

class ApiEngagementGateway implements EngagementGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function messages(ClinicalActor $actor): array
    {
        $data = $this->client->get('clinical/patient-messages', [], ['business_id' => $actor->businessId]);

        return array_values(array_filter((array) $data, 'is_array'));
    }

    public function sendMessage(ClinicalActor $actor, string $patientId, string $body, bool $isUrgent = false, ?string $threadId = null): array
    {
        return $this->client->post('clinical/patient-messages', array_filter([
            'patient_id' => $patientId,
            'thread_id' => $threadId,
            'body' => $body,
            'is_urgent' => $isUrgent,
        ], fn ($v) => $v !== null), [
            'business_id' => $actor->businessId,
            'idempotency_key' => 'patient-message-'.md5($patientId.$body.now()->format('YmdHi')),
        ]);
    }
}
