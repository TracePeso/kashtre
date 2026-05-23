<?php

namespace App\Services;

use App\Models\User;
use App\Models\StaffAssignment;
use App\Models\HrOrganizationalUnit;

class RosteringAuthorizationService
{
    /**
     * Check if a user is authorized to create a roster for a specific unit and title.
     */
    public function canGenerateRoster(User $user, HrOrganizationalUnit $unit, string|array $cadreOrDiscipline): bool
    {
        if (! $unit->isClientSpace()) {
            return false;
        }

        if ($user->canManageAllApprovals()) {
            return true;
        }

        if (! $user->staff_uuid) {
            return false;
        }

        $assignment = StaffAssignment::where('staff_uuid', $user->staff_uuid)
            ->where('organization_id', $unit->organization_id)
            ->where('organizational_unit_id', $unit->id)
            ->where('status', 'active')
            ->first();

        if (! $assignment) {
            return false;
        }

        if (! $assignment->staff_title) {
            return false;
        }

        $requestedTitles = collect(is_array($cadreOrDiscipline) ? $cadreOrDiscipline : [$cadreOrDiscipline])
            ->map(fn ($title): string => strtolower(trim((string) $title)))
            ->filter()
            ->unique()
            ->values();

        if ($requestedTitles->isEmpty()) {
            return false;
        }

        if ($requestedTitles->count() > 1) {
            return false;
        }

        return strtolower($assignment->staff_title) === $requestedTitles->first();
    }
}
