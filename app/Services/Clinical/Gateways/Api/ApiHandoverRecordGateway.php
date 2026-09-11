<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\HandoverRecordGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=api: the accountable Handover Record over HTTP —
 * SRD v6.1 Phase 9, API_GUIDE_V6.1 §Phase 9. Not to be confused with
 * ApiHandoverGateway (the stateless `GET clinical/handover` ward
 * projection) — a different resource, a different endpoint family.
 */
class ApiHandoverRecordGateway implements HandoverRecordGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function prepare(ClinicalActor $actor, array $payload): array
    {
        return $this->client->post('clinical/handovers', array_filter([
            'patient_id' => $payload['patient_id'],
            'encounter_id' => $payload['encounter_id'] ?? null,
            'intended_receiver_id' => $payload['intended_receiver_id'],
            'content' => $payload['content'],
            'transition_id' => $payload['transition_id'] ?? null,
        ], fn ($v) => $v !== null), ['business_id' => $actor->businessId]);
    }

    public function show(ClinicalActor $actor, string $handoverId): array
    {
        return $this->client->get(
            "clinical/handovers/{$handoverId}",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function send(ClinicalActor $actor, string $handoverId): array
    {
        return $this->client->post(
            "clinical/handovers/{$handoverId}/send",
            [],
            ['business_id' => $actor->businessId],
        );
    }

    public function acknowledge(ClinicalActor $actor, string $handoverId, ?string $note = null): array
    {
        return $this->client->post(
            "clinical/handovers/{$handoverId}/acknowledge",
            array_filter(['note' => $note], fn ($v) => $v !== null),
            ['business_id' => $actor->businessId],
        );
    }

    public function amend(ClinicalActor $actor, string $handoverId, array $content): array
    {
        return $this->client->post(
            "clinical/handovers/{$handoverId}/amend",
            ['content' => $content],
            ['business_id' => $actor->businessId],
        );
    }
}
