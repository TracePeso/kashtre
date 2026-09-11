<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\CareTransitionsGateway;
use App\Support\Clinical\CareTransitionRecord;
use App\Support\Clinical\ClinicalActor;
use App\Support\Clinical\DischargeDocumentRecord;
use RuntimeException;

/**
 * CLINICAL_DRIVER=local: Care Transitions (v6.1 Volume 8) has no local
 * equivalent.
 *
 * Its readiness check pulls together critical-result acknowledgement,
 * overdue scheduled observations, open MAR doses, active alerts and open
 * work orders into one deterministic evaluation — genuinely new,
 * Clinical-owned governance logic, not a thin wrapper over something Main
 * already has a local copy of (unlike, say, ward census). Reimplementing it
 * here would mean duplicating that whole engine against Main's local
 * clinical_* tables and hoping the two never disagree, to serve a code path
 * this deployment (CLINICAL_DRIVER=api) never actually takes.
 *
 * Every method refuses clearly instead. If a real local-driver deployment
 * ever needs this volume, build it as its own scoped effort against the
 * real readiness rules — not as a guess dropped in here.
 */
class LocalCareTransitionsGateway implements CareTransitionsGateway
{
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
        $this->refuse();
    }

    public function show(ClinicalActor $actor, string $transitionPublicId): CareTransitionRecord
    {
        $this->refuse();
    }

    public function completeInternalTransfer(
        ClinicalActor $actor,
        string $transitionPublicId,
        int $movementId,
        string $effectiveAt,
    ): CareTransitionRecord {
        $this->refuse();
    }

    public function issueDischargeDocument(
        ClinicalActor $actor,
        string $transitionPublicId,
        array $sections,
    ): DischargeDocumentRecord {
        $this->refuse();
    }

    public function downloadDocument(ClinicalActor $actor, string $documentPublicId): array
    {
        $this->refuse();
    }

    /**
     * @return never
     */
    private function refuse(): void
    {
        throw new RuntimeException(
            'Care Transitions (v6.1 Volume 8) is only available under CLINICAL_DRIVER=api. '
            .'Its readiness engine has no local equivalent — see LocalCareTransitionsGateway\'s docblock.'
        );
    }
}
