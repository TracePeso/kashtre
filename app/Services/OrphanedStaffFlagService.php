<?php

namespace App\Services;

use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrphanedStaffFlagService
{
    public const DEFAULT_STUCK_HOURS = 48;

    public function sweep(Organization $org, ?int $stuckHours = null): array
    {
        $cutoff = Carbon::now()->subHours($stuckHours ?? self::DEFAULT_STUCK_HOURS);

        $stuck = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->stuckInRouting($cutoff)
            ->get();

        $nullUnit = StaffAssignment::where('organization_id', $org->id)
            ->whereNull('organizational_unit_id')
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->get();

        $toFlag = $stuck->merge($nullUnit)->unique('id');

        $newlyFlagged = $this->flagAsOrphaned($toFlag);

        $totalOrphaned = StaffAssignment::where('organization_id', $org->id)
            ->orphaned()
            ->count();

        $byTier = $this->groupByCurrentTier($toFlag);

        return [
            'newly_flagged' => $newlyFlagged,
            'total_orphaned' => $totalOrphaned,
            'by_tier' => $byTier,
        ];
    }

    private function flagAsOrphaned(Collection $assignments): int
    {
        if ($assignments->isEmpty()) {
            return 0;
        }

        $count = 0;

        DB::transaction(function () use ($assignments, &$count): void {
            $now = Carbon::now();

            foreach ($assignments as $assignment) {
                $fromStatus = $assignment->status;
                $fromUnitId = $assignment->organizational_unit_id;

                $assignment->forceFill([
                    'status' => 'orphaned',
                ])->save();

                HrStaffRoutingEvent::create([
                    'staff_assignment_id' => $assignment->id,
                    'organization_id' => $assignment->organization_id,
                    'from_unit_id' => $fromUnitId,
                    'to_unit_id' => $fromUnitId,
                    'routed_by_user_id' => null,
                    'routed_by_staff_uuid' => null,
                    'from_status' => $fromStatus,
                    'to_status' => 'orphaned',
                    'routed_at' => $now,
                ]);

                $count++;
            }
        });

        return $count;
    }

    private function groupByCurrentTier(Collection $assignments): array
    {
        return $assignments
            ->groupBy(fn (StaffAssignment $assignment) => $assignment->organizational_unit_id ?? 0)
            ->map(function (Collection $group, $unitId): array {
                $unit = $unitId
                    ? HrOrganizationalUnit::with(['staffAssignments' => fn ($query) => $query
                        ->whereNotIn('status', ['inactive', 'orphaned'])
                        ->orderBy('staff_name')])
                        ->find($unitId)
                    : null;

                $routingStaffUuids = $unit
                    ? $unit->staffAssignments
                        ->pluck('staff_uuid')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()
                    : [];

                return [
                    'unit_id' => $unitId ?: null,
                    'unit_name' => $unit?->name,
                    'routing_users' => $routingStaffUuids
                        ? User::whereIn('staff_uuid', $routingStaffUuids)->get()
                        : collect(),
                    'staff' => $group->map(fn (StaffAssignment $a): array => [
                        'id' => $a->id,
                        'name' => $a->staff_name,
                        'cadre' => $a->staff_cadre,
                        'department' => $a->staff_department,
                        'title' => $a->staff_title,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
