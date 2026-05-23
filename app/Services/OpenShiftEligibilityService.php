<?php

namespace App\Services;

use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrDutyRoster;
use App\Models\HrDutyRosterEntry;
use App\Models\HrOpenShift;
use App\Models\HrStaffUnavailability;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OpenShiftEligibilityService
{
    public function __construct(
        private readonly LocumDisciplineResolver $disciplineResolver,
        private readonly RosterPolicyValidator $rosterPolicyValidator,
    ) {
    }

    public function eligibleAssignments(HrOpenShift $openShift): Collection
    {
        $disciplineKey = $this->disciplineResolver->key($openShift->discipline_key ?: $openShift->discipline_label);

        if ($disciplineKey === '') {
            return collect();
        }

        return $this->candidatePool($openShift)
            ->filter(fn (StaffAssignment $assignment): bool => $this->isEligible($openShift, $assignment, $disciplineKey))
            ->values();
    }

    public function eligibleAssignmentsForUser(HrOpenShift $openShift, User $user): Collection
    {
        if (! $user->staff_uuid) {
            return collect();
        }

        return $this->eligibleAssignments($openShift)
            ->where('staff_uuid', $user->staff_uuid)
            ->values();
    }

    public function resolveBidAssignmentForUser(HrOpenShift $openShift, User $user, ?int $selectedAssignmentId = null): ?StaffAssignment
    {
        $eligibleAssignments = $this->eligibleAssignmentsForUser($openShift, $user);

        if ($selectedAssignmentId) {
            return $eligibleAssignments->firstWhere('id', $selectedAssignmentId);
        }

        return $eligibleAssignments->first();
    }

    public function isEligible(HrOpenShift $openShift, StaffAssignment $assignment, ?string $disciplineKey = null): bool
    {
        $disciplineKey ??= $this->disciplineResolver->key($openShift->discipline_key ?: $openShift->discipline_label);

        if ($disciplineKey === '' || (int) $assignment->organization_id !== (int) $openShift->organization_id) {
            return false;
        }

        if (in_array((string) $assignment->status, ['inactive', 'orphaned', 'pending_routing'], true)) {
            return false;
        }

        if (! $this->disciplineResolver->matchesAssignment($assignment, $disciplineKey)) {
            return false;
        }

        if (! $this->candidatePool($openShift)->contains('id', $assignment->id)) {
            return false;
        }

        if ($this->hasBlockingUnavailability($openShift, $assignment)) {
            return false;
        }

        if ($this->hasConflictingRosterEntry($openShift, $assignment)) {
            return false;
        }

        return $this->passesRosterPolicyValidation($openShift, $assignment);
    }

    public function assertEligible(HrOpenShift $openShift, StaffAssignment $assignment): void
    {
        if ($this->isEligible($openShift, $assignment)) {
            return;
        }

        throw ValidationException::withMessages([
            'staff_assignment_id' => 'The selected staff member is not eligible to cover this open shift.',
        ]);
    }

    private function candidatePool(HrOpenShift $openShift): Collection
    {
        $openShift->loadMissing([
            'organization',
            'clientSpace.clientSpace',
        ]);

        $clientSpace = $openShift->clientSpace;

        if (! $clientSpace) {
            return collect();
        }

        $localAssignments = $clientSpace->staffAssignments()
            ->where('status', 'active')
            ->orderBy('staff_name')
            ->get()
            ->each(fn (StaffAssignment $assignment) => $assignment->setAttribute('locum_scope', 'local'));

        $secondaryAssignments = StaffAssignment::query()
            ->where('organization_id', $openShift->organization_id)
            ->whereNotIn('status', ['inactive', 'orphaned', 'pending_routing'])
            ->whereHas('clientSpaceStaffAssignments', function ($query) use ($clientSpace): void {
                $query
                    ->where('client_space_unit_id', $clientSpace->id)
                    ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                    ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE);
            })
            ->orderBy('staff_name')
            ->get()
            ->each(fn (StaffAssignment $assignment) => $assignment->setAttribute('locum_scope', 'local'));

        $assignments = $localAssignments
            ->concat($secondaryAssignments)
            ->unique('id')
            ->values();

        if (! $openShift->organization?->allow_cross_branch_locum_coverage) {
            return $assignments->loadMissing('organizationalUnit');
        }

        $crossBranchAssignments = StaffAssignment::query()
            ->where('organization_id', $openShift->organization_id)
            ->whereNotIn('status', ['inactive', 'orphaned', 'pending_routing'])
            ->whereKeyNot($assignments->pluck('id'))
            ->orderBy('staff_name')
            ->get()
            ->each(fn (StaffAssignment $assignment) => $assignment->setAttribute('locum_scope', 'cross_branch'));

        return $assignments
            ->concat($crossBranchAssignments)
            ->unique('id')
            ->values()
            ->loadMissing('organizationalUnit');
    }

    private function hasBlockingUnavailability(HrOpenShift $openShift, StaffAssignment $assignment): bool
    {
        return HrStaffUnavailability::query()
            ->where('organization_id', $openShift->organization_id)
            ->where('staff_assignment_id', $assignment->id)
            ->whereIn('status', [
                HrStaffUnavailability::STATUS_PENDING,
                HrStaffUnavailability::STATUS_APPROVED,
            ])
            ->where('blocks_rosters', true)
            ->whereDate('starts_on', '<=', $openShift->roster_date->toDateString())
            ->where(function ($query) use ($openShift): void {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $openShift->roster_date->toDateString());
            })
            ->exists();
    }

    private function hasConflictingRosterEntry(HrOpenShift $openShift, StaffAssignment $assignment): bool
    {
        return HrDutyRosterEntry::query()
            ->where('organization_id', $openShift->organization_id)
            ->where('staff_assignment_id', $assignment->id)
            ->whereDate('roster_date', $openShift->roster_date->toDateString())
            ->where('shift_type_id', $openShift->shift_type_id)
            ->whereHas('dutyRoster', function ($query): void {
                $query
                    ->where('status', '!=', HrDutyRoster::STATUS_ARCHIVED)
                    ->where('approval_status', '!=', HrDutyRoster::APPROVAL_REJECTED);
            })
            ->exists();
    }

    private function passesRosterPolicyValidation(HrOpenShift $openShift, StaffAssignment $assignment): bool
    {
        if (! $openShift->duty_roster_id) {
            return true;
        }

        $roster = HrDutyRoster::query()
            ->with('organizationalUnit')
            ->find($openShift->duty_roster_id);

        if (! $roster || $roster->status === HrDutyRoster::STATUS_ARCHIVED || $roster->approval_status === HrDutyRoster::APPROVAL_REJECTED) {
            return false;
        }

        try {
            $this->rosterPolicyValidator->validate($roster, [[
                'organization_id' => $openShift->organization_id,
                'roster_date' => $openShift->roster_date->toDateString(),
                'staff_assignment_id' => $assignment->id,
                'staff_uuid' => $assignment->staff_uuid,
                'staff_name' => $assignment->staff_name,
                'staff_cadre' => $assignment->staff_cadre,
                'shift_type_id' => $openShift->shift_type_id,
                'notes' => 'Locum coverage assignment',
            ]]);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }
}
