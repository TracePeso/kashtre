<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\CareAccessGateway;
use App\Services\Clinical\CareRelationshipChecker;
use App\Services\Clinical\ZtnaAccessGuard;
use App\Support\Clinical\ClinicalActor;

/**
 * CLINICAL_DRIVER=local: ReBAC and break-glass against the local
 * clinical_care_assignments / clinical_break_glass_logs tables, plus the
 * in-process ZTNA guard.
 *
 * Under this driver the checks really are enforcement — there is no server
 * behind us to re-run them. Under the API driver they degrade to advice and
 * Clinical enforces. That difference is why callers should treat a `false`
 * from here as "do not show the button" and still handle a refusal coming
 * back from the write itself.
 */
class LocalCareAccessGateway implements CareAccessGateway
{
    public function __construct(
        private readonly CareRelationshipChecker $careChecker,
        private readonly ZtnaAccessGuard $ztnaGuard,
    ) {
    }

    public function hasActiveRelationship(ClinicalActor $actor, string $patientId): bool
    {
        return $this->careChecker->hasActiveRelationship(
            $actor->userId,
            $patientId,
            $actor->businessId,
        );
    }

    public function myPatientIds(ClinicalActor $actor): array
    {
        return $this->careChecker->myPatientClientIds($actor->userId, $actor->businessId);
    }

    public function claim(ClinicalActor $actor, string $role, string $patientId, ?string $visitId = null): void
    {
        $this->careChecker->claimIndividually(
            $actor->userId,
            $role,
            $patientId,
            $visitId,
            $actor->businessId,
            $actor->branchId,
        );
    }

    public function grantBreakGlass(
        ClinicalActor $actor,
        string $patientId,
        ?string $visitId,
        string $reasonCode,
        ?string $justificationNote = null,
    ): void {
        $this->ztnaGuard->grantBreakGlass(
            $actor->userId,
            $actor->businessId,
            $patientId,
            $visitId,
            $reasonCode,
            $justificationNote,
        );
    }

    public function canMutateFromCurrentLocation(): bool
    {
        // No request bound (queue worker, scheduled command) means this is
        // module traffic rather than a clinician at a terminal, and the
        // off-premises restriction does not apply to it.
        if (! app()->bound('request') || app('request') === null) {
            return true;
        }

        return $this->ztnaGuard->isOnPremises(request());
    }
}
