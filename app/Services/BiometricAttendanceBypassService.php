<?php

namespace App\Services;

use App\Models\HrBiometricProfile;
use App\Models\HrStaffUnavailability;
use App\Models\Organization;
use App\Models\StaffAssignment;
use Illuminate\Http\Request;

class BiometricAttendanceBypassService
{
    public function approvedOffsiteDutyForRequest(Request $request, Organization $organization): ?HrStaffUnavailability
    {
        $staffAssignmentIds = $this->staffAssignmentIdsForRequest($request, $organization);

        if ($staffAssignmentIds === []) {
            return null;
        }

        $today = now()->toDateString();

        return HrStaffUnavailability::query()
            ->where('organization_id', $organization->id)
            ->whereIn('staff_assignment_id', $staffAssignmentIds)
            ->where('reason_type', HrStaffUnavailability::REASON_OFFSITE_DUTY)
            ->where('status', HrStaffUnavailability::STATUS_APPROVED)
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->orderBy('starts_on')
            ->first();
    }

    /**
     * @return array<int, int>
     */
    private function staffAssignmentIdsForRequest(Request $request, Organization $organization): array
    {
        $assignmentIds = [];

        $requestAssignmentId = $request->input('staff_assignment_id');

        if (is_numeric($requestAssignmentId)) {
            $assignmentIds[] = (int) $requestAssignmentId;
        }

        $profileUuid = $request->input('profile_uuid');

        if (is_string($profileUuid) && $profileUuid !== '') {
            $profileAssignmentId = HrBiometricProfile::query()
                ->where('organization_id', $organization->id)
                ->where('uuid', $profileUuid)
                ->value('staff_assignment_id');

            if ($profileAssignmentId) {
                $assignmentIds[] = (int) $profileAssignmentId;
            }
        }

        $staffUuid = $request->user()?->staff_uuid;

        if (is_string($staffUuid) && $staffUuid !== '') {
            $assignmentIds = array_merge($assignmentIds, StaffAssignment::query()
                ->where('organization_id', $organization->id)
                ->where('staff_uuid', $staffUuid)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all());
        }

        return array_values(array_unique(array_filter($assignmentIds)));
    }
}
