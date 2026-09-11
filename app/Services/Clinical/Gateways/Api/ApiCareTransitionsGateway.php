<?php

namespace App\Services\Clinical\Gateways\Api;

use App\Contracts\Clinical\CareTransitionsGateway;
use App\Services\Clinical\Api\ClinicalApiClient;
use App\Support\Clinical\CareTransitionRecord;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\DischargeDocumentRecord;

/**
 * CLINICAL_DRIVER=api: Care Transitions (v6.1 Volume 8) — the only volume
 * from the v6.1 EDD engagement with a real, Main-callable endpoint group.
 * Base path `/api/v1/clinical/care-transitions`. Gate is service key + ZTNA
 * only — no automatic care-relationship or chart-lock check at the route
 * layer (unlike the older `/clinical/transitions/*` path).
 */
class ApiCareTransitionsGateway implements CareTransitionsGateway
{
    public function __construct(private readonly ClinicalApiClient $client)
    {
    }

    public function start(
        ClinicalActor $actor,
        string $patientId,
        ?string $encounterId,
        string $type,
        ?int $fromClientSpaceId = null,
        ?int $toClientSpaceId = null,
        ?string $destinationOrganizationId = null,
        ?string $plannedAt = null,
        ?string $reason = null,
        array $context = [],
    ): CareTransitionRecord {
        $data = $this->client->post('clinical/care-transitions', array_filter([
            'patient_id' => $patientId,
            'encounter_id' => $encounterId,
            'type' => $type,
            'from_client_space_id' => $fromClientSpaceId,
            'to_client_space_id' => $toClientSpaceId,
            'destination_organization_id' => $destinationOrganizationId,
            'planned_at' => $plannedAt,
            'reason' => $reason,
            'context' => $context !== [] ? $context : null,
        ], fn ($value) => $value !== null), [
            'business_id' => $actor->businessId,
            // One key per genuine initiation attempt. Derived from the
            // caller's own inputs (not a fresh uuid) so a retried request —
            // a tablet losing signal right after tapping "Start" — replays
            // the same transition instead of opening a second one for the
            // same patient/encounter/type.
            'idempotency_key' => 'care-transition-start-'.md5($patientId.'|'.$encounterId.'|'.$type.'|'.$plannedAt),
        ]);

        return CareTransitionRecord::fromApi($data);
    }

    public function show(ClinicalActor $actor, string $transitionPublicId): CareTransitionRecord
    {
        $data = $this->client->get(
            "clinical/care-transitions/{$transitionPublicId}",
            [],
            ['business_id' => $actor->businessId],
        );

        return CareTransitionRecord::fromApi($data);
    }

    public function completeInternalTransfer(
        ClinicalActor $actor,
        string $transitionPublicId,
        int $movementId,
        string $effectiveAt,
    ): CareTransitionRecord {
        $data = $this->client->post(
            "clinical/care-transitions/{$transitionPublicId}/internal-transfer/complete",
            [
                'movement_id' => $movementId,
                'effective_at' => $effectiveAt,
            ],
            [
                'business_id' => $actor->businessId,
                'idempotency_key' => "care-transition-{$transitionPublicId}-complete-transfer-{$movementId}",
            ],
        );

        return CareTransitionRecord::fromApi($data);
    }

    public function issueDischargeDocument(
        ClinicalActor $actor,
        string $transitionPublicId,
        array $sections,
    ): DischargeDocumentRecord {
        $data = $this->client->post(
            "clinical/care-transitions/{$transitionPublicId}/discharge-document",
            ['sections' => (object) $sections],
            [
                'business_id' => $actor->businessId,
                // A document is immutable once attested (no correction
                // endpoint) — a retry under the same key must replay the
                // one already issued, never mint a second attested version
                // of the same discharge.
                'idempotency_key' => "care-transition-{$transitionPublicId}-discharge-document",
            ],
        );

        return DischargeDocumentRecord::fromApi($data);
    }

    public function downloadDocument(ClinicalActor $actor, string $documentPublicId): array
    {
        // download() already exists for exactly this (IPS exports, §13) —
        // a binary body decode() would choke on trying to parse as JSON.
        $body = $this->client->download(
            "clinical/care-transitions/documents/{$documentPublicId}/pdf",
            [],
            ['business_id' => $actor->businessId, 'accept' => 'application/pdf'],
        );

        // The guide is explicit this endpoint always answers
        // Content-Type: application/pdf — no header round-trip needed.
        return ['body' => $body, 'content_type' => 'application/pdf'];
    }
}
