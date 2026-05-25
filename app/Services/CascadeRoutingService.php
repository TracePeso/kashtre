<?php

namespace App\Services;

use App\Models\HrClientSpaceStaffAssignment;
use App\Models\StaffAssignment;
use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRoutingEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CascadeRoutingService
{
    /**
     * Route a staff assignment to a specific organizational unit.
     */
    public function routeStaff(StaffAssignment $assignment, HrOrganizationalUnit $targetUnit, User $routedBy)
    {
        if ($assignment->organization_id !== $targetUnit->organization_id) {
            throw ValidationException::withMessages(['unit' => 'Cannot route staff across different organizations.']);
        }

        $assignment->loadMissing('organizationalUnit');
        $currentUnit = $assignment->organizationalUnit;

        $this->assertCanRoute($currentUnit, $targetUnit);

        DB::transaction(function () use ($assignment, $currentUnit, $targetUnit, $routedBy) {
            $fromStatus = $assignment->status;
            $routedAt = now();
            $toStatus = 'active';

            $assignment->organizational_unit_id = $targetUnit->id;
            $assignment->routed_by_user_id = $routedBy->id;
            $assignment->routed_by_staff_uuid = $routedBy->staff_uuid;
            $assignment->routed_at = $routedAt;
            $assignment->status = $toStatus;
            $assignment->save();

            if ($currentUnit?->isClientSpace() && ! $targetUnit->isClientSpace()) {
                HrClientSpaceStaffAssignment::query()
                    ->where('organization_id', $assignment->organization_id)
                    ->where('client_space_unit_id', $currentUnit->id)
                    ->where('staff_assignment_id', $assignment->id)
                    ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_PRIMARY)
                    ->update([
                        'status' => HrClientSpaceStaffAssignment::STATUS_INACTIVE,
                        'updated_at' => now(),
                    ]);
            }

            if ($targetUnit->isClientSpace()) {
                HrClientSpaceStaffAssignment::query()->updateOrCreate(
                    [
                        'organization_id' => $assignment->organization_id,
                        'client_space_unit_id' => $targetUnit->id,
                        'staff_assignment_id' => $assignment->id,
                    ],
                    [
                        'staff_uuid' => $assignment->staff_uuid,
                        'assignment_type' => HrClientSpaceStaffAssignment::TYPE_PRIMARY,
                        'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
                        'assigned_by_user_id' => $routedBy->id,
                        'assigned_at' => $routedAt,
                    ]
                );
            }

            HrStaffRoutingEvent::create([
                'staff_assignment_id' => $assignment->id,
                'organization_id' => $assignment->organization_id,
                'from_unit_id' => $currentUnit?->id,
                'to_unit_id' => $targetUnit->id,
                'routed_by_user_id' => $routedBy->id,
                'routed_by_staff_uuid' => $routedBy->staff_uuid,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'routed_at' => $routedAt,
            ]);
        });

        return $assignment;
    }

    public function availableTargetsForAssignment(StaffAssignment $assignment): Collection
    {
        $assignment->loadMissing('organizationalUnit');
        $currentUnit = $assignment->organizationalUnit;

        if (! $currentUnit) {
            return HrOrganizationalUnit::where('organization_id', $assignment->organization_id)
                ->routingNodes()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();
        }

        if ($currentUnit->isClientSpace()) {
            return collect();
        }

        $routingNodes = HrOrganizationalUnit::where('organization_id', $assignment->organization_id)
            ->routingNodes()
            ->where('parent_id', $currentUnit->id)
            ->get();

        $clientSpaces = HrOrganizationalUnit::where('organization_id', $assignment->organization_id)
            ->clientSpaces()
            ->where(function ($query) use ($currentUnit): void {
                $query->where('parent_id', $currentUnit->id)
                    ->orWhereHas('clientSpaceRoutes', fn ($routeQuery) => $routeQuery->where('routing_unit_id', $currentUnit->id));
            })
            ->get();

        return $routingNodes
            ->concat($clientSpaces)
            ->unique('id')
            ->sortBy(fn (HrOrganizationalUnit $unit): string => strtolower($unit->name))
            ->values();
    }

    private function assertCanRoute(
        ?HrOrganizationalUnit $currentUnit,
        HrOrganizationalUnit $targetUnit,
    ): void {
        if ($currentUnit) {
            if ($currentUnit->isClientSpace()) {
                if (! $targetUnit->isRoutingNode()) {
                    throw ValidationException::withMessages(['unit' => 'Staff in a client space must be moved back to a routing node first.']);
                }

                return;
            }

            if ($targetUnit->isClientSpace()) {
                if (! $targetUnit->isLinkedToRoutingNode($currentUnit)) {
                    if ((int) $targetUnit->parent_id !== (int) $currentUnit->id) {
                        throw ValidationException::withMessages(['unit' => 'Staff can only be routed to the next tier below the current unit.']);
                    }

                    throw ValidationException::withMessages(['unit' => 'Staff can only be routed into client spaces linked to the current routing node.']);
                }

                return;
            }

            if ((int) $targetUnit->parent_id !== (int) $currentUnit->id) {
                throw ValidationException::withMessages(['unit' => 'Staff can only be routed to the next tier below the current unit.']);
            }

            return;
        }

        if ($targetUnit->isClientSpace()) {
            throw ValidationException::withMessages(['unit' => 'Staff from the global pool cannot be routed directly into a client space.']);
        }
    }
}
