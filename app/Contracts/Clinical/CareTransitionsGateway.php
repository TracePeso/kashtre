<?php

namespace App\Contracts\Clinical;

use App\Support\Clinical\CareTransitionRecord;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\DischargeDocumentRecord;

/**
 * Care Transitions — API_GUIDE_V6.1 §1 (Volume 8), the one v6.1 volume that
 * ships as a fully live, Main-callable endpoint group.
 *
 * A new governance layer sitting *alongside* the older `/clinical/transitions/*`
 * step-execution flow (`ProcessExecutionGateway`) — not replacing it. The old
 * flow still does the actual bed allocation, order halting and chart locking;
 * this one adds a pre-authorization readiness gate, attested versioned
 * discharge documents, and re-anchoring of scheduled-observation obligations
 * on a ward move.
 *
 * There is no list endpoint for a patient's transitions and no recheck/
 * refresh action — readiness is evaluated once, at `start()`. Main keeps its
 * own local index of what it started (`ClinicalCareTransition`) purely so a
 * panel is not blind on every page reload; that index is not part of this
 * contract.
 */
interface CareTransitionsGateway
{
    /**
     * Starts a transition and runs its readiness check in one call. The
     * response's `status` (READY/READINESS_CHECK) does not carry *why* —
     * follow immediately with `show()` for the readiness detail.
     */
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
    ): CareTransitionRecord;

    public function show(ClinicalActor $actor, string $transitionPublicId): CareTransitionRecord;

    /**
     * Accepts the BedMovement id from the bed move already done through the
     * existing `WardCensusGateway::assignBed()`/`ProcessExecutionGateway`
     * flow as evidence it happened — this does not move the bed itself, and
     * re-anchors the patient's Observation Plan obligations to the new
     * location.
     */
    public function completeInternalTransfer(
        ClinicalActor $actor,
        string $transitionPublicId,
        int $movementId,
        string $effectiveAt,
    ): CareTransitionRecord;

    /**
     * @param  array<string, string>  $sections  keyed by section code — an
     *                                            object, not a list. Refused
     *                                            unless the transition is
     *                                            type DISCHARGE and status
     *                                            is exactly READY. The
     *                                            result is immutable once
     *                                            attested — there is no
     *                                            correction endpoint.
     */
    public function issueDischargeDocument(
        ClinicalActor $actor,
        string $transitionPublicId,
        array $sections,
    ): DischargeDocumentRecord;

    /**
     * @return array{body: string, content_type: string}
     */
    public function downloadDocument(ClinicalActor $actor, string $documentPublicId): array;
}
