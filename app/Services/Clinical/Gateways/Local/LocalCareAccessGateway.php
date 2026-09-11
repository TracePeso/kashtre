<?php

namespace App\Services\Clinical\Gateways\Local;

use App\Contracts\Clinical\CareAccessGateway;
use App\Models\ClinicalCareAssignment;
use App\Models\ClinicalCareTeam;
use App\Services\Clinical\CareRelationshipChecker;
use App\Services\Clinical\ZtnaAccessGuard;
use App\Support\Clinical\ClinicalActor;
use RuntimeException;

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
        // Via the guard rather than the checker directly, so an unexpired
        // break-glass grant counts as access. Clinical folds its own override
        // window into the same answer, and a clinician who has just broken
        // glass must not be bounced straight back to the refusal screen.
        return $this->ztnaGuard->hasAccess(
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
    ): ?string {
        $this->ztnaGuard->grantBreakGlass(
            $actor->userId,
            $actor->businessId,
            $patientId,
            $visitId,
            $reasonCode,
            $justificationNote,
        );

        // BreakGlassEpisode (v6.1 Volume 9) is Clinical-owned; there is no
        // local equivalent, so there is nothing to review under this driver.
        return null;
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

    /**
     * The schema behind this driver (clinical_care_assignments) has no
     * participation column and only ever holds one active row per patient —
     * there is no local concept of CO_MANAGING, only whoever currently
     * "is" the assignment. This shapes what it has into the same fields the
     * API's richer response carries, leaving team/co-managing empty rather
     * than inventing data the local schema does not track.
     */
    public function teamFor(ClinicalActor $actor, string $patientId, ?string $visitId = null): array
    {
        $assignment = ClinicalCareAssignment::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->where('is_active', true)
            ->with('assignedTeam.members')
            ->first();

        if (! $assignment) {
            return ['patient_id' => $patientId, 'assignment' => null, 'members' => [], 'co_managing_count' => 0, 'is_unassigned' => true];
        }

        $members = [];

        if ($assignment->primary_doctor_user_id) {
            $members[] = ['user_id' => $assignment->primary_doctor_user_id, 'role_code' => 'PRIMARY_DOCTOR', 'participation' => 'PRIMARY'];
        }

        if ($assignment->primary_nurse_user_id) {
            $members[] = ['user_id' => $assignment->primary_nurse_user_id, 'role_code' => 'PRIMARY_NURSE', 'participation' => 'PRIMARY'];
        }

        foreach ($assignment->assignedTeam?->members ?? [] as $teamMember) {
            $members[] = ['user_id' => $teamMember->user_id, 'role_code' => $teamMember->role_code, 'participation' => 'PRIMARY'];
        }

        return [
            'patient_id' => $patientId,
            'assignment' => $assignment->toArray(),
            'team' => $assignment->assignedTeam?->team_name,
            'members' => $members,
            'co_managing_count' => 0,
            'is_unassigned' => false,
        ];
    }

    public function assign(ClinicalActor $actor, array $attributes): void
    {
        if (($attributes['participation'] ?? 'PRIMARY') === 'CO_MANAGING') {
            // A second, non-displacing responsible clinician has nowhere to
            // live in this schema — clinical_care_assignments has no
            // participation column and is a single active row per patient.
            // Refusing loudly here is more honest than silently overwriting
            // the primary assignment, which is what create-or-update would
            // otherwise do.
            throw new RuntimeException('CO_MANAGING assignments are not supported under the local clinical driver.');
        }

        $patientId = (string) ($attributes['patient_id'] ?? '');

        ClinicalCareAssignment::where('business_id', $actor->businessId)
            ->where('client_id', $patientId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $teamId = $attributes['assigned_team_id'] ?? null;

        if ($teamId && ! ClinicalCareTeam::where('business_id', $actor->businessId)->whereKey($teamId)->exists()) {
            throw new RuntimeException("Care team {$teamId} was not found for this business.");
        }

        ClinicalCareAssignment::create([
            'business_id' => $actor->businessId,
            'branch_id' => $actor->branchId,
            'client_id' => $patientId,
            'visit_id' => $attributes['visit_id'] ?? null,
            'assignment_model' => $attributes['assignment_model'] ?? ClinicalCareAssignment::MODEL_INDIVIDUAL,
            'primary_doctor_user_id' => $attributes['primary_doctor_id'] ?? null,
            'primary_nurse_user_id' => $attributes['primary_nurse_id'] ?? null,
            'assigned_team_id' => $teamId,
            'assigned_role_code' => $attributes['assigned_role_code'] ?? null,
            'is_active' => true,
        ]);
    }

    public function endAssignment(ClinicalActor $actor, int|string $assignmentId): void
    {
        ClinicalCareAssignment::where('business_id', $actor->businessId)
            ->whereKey($assignmentId)
            ->update(['is_active' => false]);
    }

    public function reviewBreakGlass(
        ClinicalActor $actor,
        string $episodePublicId,
        string $outcome,
        string $finding,
    ): void {
        // BreakGlassEpisode (v6.1 Volume 9) is Clinical-owned; grantBreakGlass()
        // above already returns null under this driver, so no caller should
        // ever have a real episode id to review here.
        throw new \RuntimeException(
            'Break-glass independent review (v6.1 Volume 9) is only available under CLINICAL_DRIVER=api.'
        );
    }
}
