<?php

namespace App\Services\Clinical;

use App\Models\ClinicalCareAssignment;
use App\Models\ClinicalCareTeamMember;

/**
 * ReBAC (Relationship-Based Access Control) check from the Engineering
 * doc's ZtnaContextMiddleware::checkCareRelationship — split out as a
 * standalone service so it can be used without the full ZTNA middleware
 * (off-premises blocking, break-glass override, watermarking), which is
 * deferred to Chunk 9.
 */
class CareRelationshipChecker
{
    public function hasActiveRelationship(int $userId, string $clientId, int $businessId): bool
    {
        return ClinicalCareAssignment::query()
            ->where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->where(function ($query) use ($userId) {
                $query->where('primary_doctor_user_id', $userId)
                    ->orWhere('primary_nurse_user_id', $userId)
                    ->orWhereIn('assigned_team_id', function ($sub) use ($userId) {
                        $sub->select('team_id')
                            ->from('clinical_care_team_members')
                            ->where('user_id', $userId);
                    });
            })
            ->exists();
    }

    /**
     * SRD §3.2 "My Patients" — every client_id the user has an active
     * individual or team-based relationship with. Chunk 5's Task
     * Visibility board filters the Main Module's enterprise queues down
     * to this set instead of duplicating them.
     *
     * @return array<int, string>
     */
    public function myPatientClientIds(int $userId, int $businessId): array
    {
        return ClinicalCareAssignment::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->where(function ($query) use ($userId) {
                $query->where('primary_doctor_user_id', $userId)
                    ->orWhere('primary_nurse_user_id', $userId)
                    ->orWhereIn('assigned_team_id', function ($sub) use ($userId) {
                        $sub->select('team_id')
                            ->from('clinical_care_team_members')
                            ->where('user_id', $userId);
                    });
            })
            ->pluck('client_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * MVP "claim this patient" helper (Chunk 2) — creates or reactivates a
     * simple INDIVIDUAL assignment for the acting user. The full Care
     * Assignment management UI (role/team/hybrid models, reassignment,
     * handover) is Chunk 5's Task Visibility & Major Transitions work.
     */
    public function claimIndividually(int $userId, string $role, string $clientId, ?string $visitId, int $businessId, ?int $branchId = null): ClinicalCareAssignment
    {
        $column = $role === 'doctor' ? 'primary_doctor_user_id' : 'primary_nurse_user_id';

        $assignment = ClinicalCareAssignment::query()
            ->where('business_id', $businessId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            $assignment = new ClinicalCareAssignment([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'assignment_model' => ClinicalCareAssignment::MODEL_INDIVIDUAL,
                'is_active' => true,
            ]);
        }

        $assignment->visit_id = $visitId ?? $assignment->visit_id;
        $assignment->{$column} = $userId;
        $assignment->save();

        return $assignment;
    }
}
