<?php

namespace App\Livewire;

use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\CascadeRoutingService;
use App\Services\ClientSpaceRoutingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ClientSpaceDirectory extends Component
{
    public bool $showAddStaffModal = false;
    public bool $showSecondaryAssignmentModal = false;
    public ?int $selectedClientSpaceId = null;
    public array $selectedStaffAssignmentIds = [];
    public ?int $selectedSecondaryStaffAssignmentId = null;
    public bool $showPlacementModal = false;
    public ?int $selectedPlacementClientSpaceId = null;
    public ?int $primaryRoutingUnitId = null;
    public array $linkedRoutingUnitIds = [];
    public string $routingUnitSearch = '';
    public bool $canManageClientSpaceStaff = false;
    public bool $canManageSecondaryAssignments = false;
    public bool $canManageClientSpacePlacement = false;

    public function mount(): void
    {
        $this->setPermissions();
    }

    public function openAddStaffModal(int $clientSpaceId): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->findOrFail($clientSpaceId);

        abort_unless($this->canManageClientSpace($clientSpace), 403);

        $this->resetValidation();
        $this->selectedClientSpaceId = $clientSpaceId;
        $this->selectedStaffAssignmentIds = [];
        $this->showAddStaffModal = true;
    }

    public function openSecondaryAssignmentModal(int $clientSpaceId): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->findOrFail($clientSpaceId);

        abort_unless($this->canManageSecondaryAssignments(), 403);

        $this->resetValidation();
        $this->selectedClientSpaceId = $clientSpace->id;
        $this->selectedSecondaryStaffAssignmentId = null;
        $this->showSecondaryAssignmentModal = true;
    }

    public function addSecondaryAssignment(): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'selectedClientSpaceId' => 'required|integer|exists:hr_organizational_units,id',
            'selectedSecondaryStaffAssignmentId' => 'required|integer|exists:hr_staff_assignments,id',
        ]);

        abort_unless($this->canManageSecondaryAssignments(), 403);

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->findOrFail($this->selectedClientSpaceId);

        $assignment = StaffAssignment::where('organization_id', $org->id)
            ->findOrFail($this->selectedSecondaryStaffAssignmentId);

        if (in_array($assignment->status, ['inactive', 'orphaned'], true)) {
            $this->addError('selectedSecondaryStaffAssignmentId', 'Inactive or orphaned staff cannot be added as secondary clinical staff.');

            return;
        }

        if ((int) $assignment->organizational_unit_id === (int) $clientSpace->id) {
            $this->addError('selectedSecondaryStaffAssignmentId', 'This staff member is already primarily assigned to this client space.');

            return;
        }

        $activeExisting = HrClientSpaceStaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('client_space_unit_id', $clientSpace->id)
            ->where('staff_assignment_id', $assignment->id)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->exists();

        if ($activeExisting) {
            $this->addError('selectedSecondaryStaffAssignmentId', 'This staff member is already linked to this client space.');

            return;
        }

        HrClientSpaceStaffAssignment::query()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'client_space_unit_id' => $clientSpace->id,
                'staff_assignment_id' => $assignment->id,
            ],
            [
                'staff_uuid' => $assignment->staff_uuid,
                'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
                'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
                'assigned_by_user_id' => Auth::id(),
                'assigned_at' => now(),
            ]
        );

        $this->showSecondaryAssignmentModal = false;
        $this->selectedClientSpaceId = null;
        $this->selectedSecondaryStaffAssignmentId = null;

        session()->flash('message', 'Additional client space assignment added.');
    }

    public function addStaffToClientSpace(): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'selectedClientSpaceId' => 'required|integer|exists:hr_organizational_units,id',
            'selectedStaffAssignmentIds' => 'required|array|min:1',
            'selectedStaffAssignmentIds.*' => 'integer|distinct|exists:hr_staff_assignments,id',
        ]);

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->findOrFail($this->selectedClientSpaceId);

        $user = Auth::user();
        abort_unless($user && $this->canManageClientSpace($clientSpace), 403);

        $selectedStaffAssignmentIds = collect($this->selectedStaffAssignmentIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $assignments = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->whereIn('id', $selectedStaffAssignmentIds)
            ->get()
            ->keyBy('id');

        if ($assignments->count() !== $selectedStaffAssignmentIds->count()) {
            $this->addError('selectedStaffAssignmentIds', 'Select valid staff from the current organization.');

            return;
        }

        if ($assignments->contains(fn (StaffAssignment $assignment): bool => in_array($assignment->status, ['inactive', 'orphaned'], true))) {
            $this->addError('selectedStaffAssignmentIds', 'Inactive staff cannot be added to a client space.');

            return;
        }

        if ($assignments->contains(fn (StaffAssignment $assignment): bool => (int) $assignment->organizational_unit_id === (int) $clientSpace->id)) {
            $this->addError('selectedStaffAssignmentIds', 'One or more selected staff members are already in this client space.');

            return;
        }

        if (! $clientSpace->parent_id) {
            $this->addError('selectedStaffAssignmentIds', 'Attach this client space to a primary routing node before adding staff directly.');

            return;
        }

        $primaryRouteId = (int) $clientSpace->parent_id;

        if ($assignments->contains(fn (StaffAssignment $assignment): bool => (int) $assignment->organizational_unit_id !== $primaryRouteId)) {
            $this->addError('selectedStaffAssignmentIds', 'Only staff currently under this client space\'s primary routing node can be added directly. Use additional assignments for everyone else.');

            return;
        }

        $activeExisting = HrClientSpaceStaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('client_space_unit_id', $clientSpace->id)
            ->whereIn('staff_assignment_id', $selectedStaffAssignmentIds)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->exists();

        if ($activeExisting) {
            $this->addError('selectedStaffAssignmentIds', 'One or more selected staff members are already linked to this client space.');

            return;
        }

        $selectedStaffAssignmentIds
            ->map(fn (int $id): StaffAssignment => $assignments->get($id))
            ->each(fn (StaffAssignment $assignment) => $this->moveStaffIntoClientSpace($assignment, $clientSpace, $user));

        $this->showAddStaffModal = false;
        $this->selectedClientSpaceId = null;
        $this->selectedStaffAssignmentIds = [];

        session()->flash('message', $selectedStaffAssignmentIds->count() === 1
            ? 'Staff added to client space.'
            : 'Selected staff added to client space.');
    }

    public function updatedSelectedStaffAssignmentIds($value = null): void
    {
        $this->selectedStaffAssignmentIds = collect($this->selectedStaffAssignmentIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function selectAllVisibleStaff(): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->selectedStaffAssignmentIds = collect($this->selectedStaffAssignmentIds)
            ->merge($this->availableStaffOptions($org)->keys()->map(fn ($id): int => (int) $id))
            ->unique()
            ->values()
            ->all();
    }

    public function clearStaffSelection(): void
    {
        $this->selectedStaffAssignmentIds = [];
    }

    private function moveStaffIntoClientSpace(StaffAssignment $assignment, HrOrganizationalUnit $clientSpace, $user): void
    {
        $assignment->loadMissing('organizationalUnit');
        $currentUnit = $assignment->organizationalUnit;
        $fromStatus = $assignment->status;
        $routedAt = now();

        DB::transaction(function () use ($assignment, $clientSpace, $user, $currentUnit, $fromStatus, $routedAt): void {
            if ($currentUnit?->isClientSpace()) {
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

            $assignment->forceFill([
                'organizational_unit_id' => $clientSpace->id,
                'routed_by_user_id' => $user->id,
                'routed_by_staff_uuid' => $user->staff_uuid,
                'routed_at' => $routedAt,
                'status' => 'active',
            ])->save();

            HrClientSpaceStaffAssignment::query()->updateOrCreate(
                [
                    'organization_id' => $assignment->organization_id,
                    'client_space_unit_id' => $clientSpace->id,
                    'staff_assignment_id' => $assignment->id,
                ],
                [
                    'staff_uuid' => $assignment->staff_uuid,
                    'assignment_type' => HrClientSpaceStaffAssignment::TYPE_PRIMARY,
                    'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
                    'assigned_by_user_id' => $user->id,
                    'assigned_at' => $routedAt,
                ]
            );

            HrStaffRoutingEvent::create([
                'staff_assignment_id' => $assignment->id,
                'organization_id' => $assignment->organization_id,
                'from_unit_id' => $currentUnit?->id,
                'to_unit_id' => $clientSpace->id,
                'routed_by_user_id' => $user->id,
                'routed_by_staff_uuid' => $user->staff_uuid,
                'from_status' => $fromStatus,
                'to_status' => 'active',
                'routed_at' => $routedAt,
            ]);
        });
    }

    public function removePrimaryStaffFromClientSpace(int $clientSpaceId, int $staffAssignmentId): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->findOrFail($clientSpaceId);

        $user = Auth::user();
        abort_unless($user && $this->canManageClientSpace($clientSpace), 403);

        $assignment = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->where('organizational_unit_id', $clientSpace->id)
            ->findOrFail($staffAssignmentId);

        if (! $clientSpace->parent_id) {
            $this->addError('selectedStaffAssignmentIds', 'Attach a primary route before removing staff from this client space.');

            return;
        }

        $returnRoute = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->findOrFail($clientSpace->parent_id);

        try {
            app(CascadeRoutingService::class)->routeStaff($assignment, $returnRoute, $user);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError('selectedStaffAssignmentIds', $message);
                }
            }

            return;
        }

        session()->flash('message', 'Staff removed from client space.');
    }

    public function removeSecondaryStaffFromClientSpace(int $clientSpaceId, int $staffAssignmentId): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        abort_unless($this->canManageSecondaryAssignments(), 403);

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->findOrFail($clientSpaceId);

        $updated = HrClientSpaceStaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('client_space_unit_id', $clientSpace->id)
            ->where('staff_assignment_id', $staffAssignmentId)
            ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->update([
                'status' => HrClientSpaceStaffAssignment::STATUS_INACTIVE,
                'updated_at' => now(),
            ]);

        abort_unless($updated > 0, 404);

        session()->flash('message', 'Additional staff removed from client space.');
    }

    public function openPlacementModal(int $clientSpaceId): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['routingParents', 'clientSpaceRoutes'])
            ->findOrFail($clientSpaceId);

        abort_unless($this->canManagePlacement($clientSpace), 403);

        $this->resetValidation();
        $lowestRoutingUnitIds = $this->lowestRoutingUnitIdsForPlacement($org);
        $this->selectedPlacementClientSpaceId = $clientSpaceId;
        $this->primaryRoutingUnitId = $lowestRoutingUnitIds->contains((int) $clientSpace->parent_id)
            ? (int) $clientSpace->parent_id
            : null;
        $this->linkedRoutingUnitIds = $this->normalizeLinkedRoutingUnitIds($clientSpace->routingParents
            ->pluck('id')
            ->when($this->primaryRoutingUnitId !== null, fn (Collection $ids): Collection => $ids->push($this->primaryRoutingUnitId))
            ->all(), $lowestRoutingUnitIds);
        $this->routingUnitSearch = '';
        $this->showPlacementModal = true;
    }

    public function updatedPrimaryRoutingUnitId($value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $primaryRoutingUnitId = (int) $value;

        $this->linkedRoutingUnitIds = collect($this->linkedRoutingUnitIds)
            ->map(fn ($id): int => (int) $id)
            ->push($primaryRoutingUnitId)
            ->unique()
            ->values()
            ->all();
    }

    public function updatedLinkedRoutingUnitIds($value = null): void
    {
        $this->linkedRoutingUnitIds = collect($this->linkedRoutingUnitIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($this->primaryRoutingUnitId !== null && ! in_array((int) $this->primaryRoutingUnitId, $this->linkedRoutingUnitIds, true)) {
            $this->primaryRoutingUnitId = null;
        }
    }

    public function selectAllVisibleUnits(): void
    {
        $org = Organization::current();
        if (! $org || ! $this->canManageClientSpacePlacement) {
            return;
        }

        $visibleRoutingUnitIds = $this->routingUnitOptionsForOrganization($org)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $this->linkedRoutingUnitIds = collect($this->linkedRoutingUnitIds)
            ->merge(
                $visibleRoutingUnitIds
            )
            ->intersect($this->lowestRoutingUnitIdsForPlacement($org))
            ->values()
            ->all();
    }

    public function clearUnits(): void
    {
        $this->linkedRoutingUnitIds = [];
        $this->primaryRoutingUnitId = null;
    }

    public function savePlacement(ClientSpaceRoutingService $clientSpaceRoutingService): void
    {
        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'selectedPlacementClientSpaceId' => 'required|integer|exists:hr_organizational_units,id',
            'primaryRoutingUnitId' => 'nullable|integer|exists:hr_organizational_units,id',
            'linkedRoutingUnitIds' => 'array',
            'linkedRoutingUnitIds.*' => 'integer|exists:hr_organizational_units,id',
        ]);

        $clientSpace = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->findOrFail($this->selectedPlacementClientSpaceId);

        abort_unless($this->canManagePlacement($clientSpace), 403);

        $linkedRoutingUnitIds = collect($this->linkedRoutingUnitIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($linkedRoutingUnitIds->isNotEmpty() && ! $this->primaryRoutingUnitId) {
            $this->addError('primaryRoutingUnitId', 'Choose which selected unit is the primary route before saving.');

            return;
        }

        if ($this->primaryRoutingUnitId) {
            $linkedRoutingUnitIds = $linkedRoutingUnitIds
                ->push((int) $this->primaryRoutingUnitId)
                ->unique()
                ->values();
        }

        try {
            $clientSpaceRoutingService->syncRoutes(
                $clientSpace,
                $linkedRoutingUnitIds->all(),
                $this->primaryRoutingUnitId
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->resetPlacementModal();
        session()->flash('message', 'Client space routes updated.');
    }

    private function canManageClientSpace(HrOrganizationalUnit $clientSpace): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->is_hr_admin) {
            return true;
        }

        if ($user->canAddHrStaff() || $user->canEditHrStaff()) {
            return true;
        }

        $routingParents = $clientSpace->relationLoaded('routingParents')
            ? $clientSpace->routingParents
            : $clientSpace->routingParents()->get();

        if ($routingParents->isNotEmpty()) {
            return $routingParents->contains(
                fn (HrOrganizationalUnit $parent): bool => $parent->hasRoutingMember($user)
            );
        }

        $parent = $clientSpace->relationLoaded('parent') ? $clientSpace->parent : $clientSpace->parent()->first();

        return $parent ? $parent->hasRoutingMember($user) : false;
    }

    public function render(): View
    {
        $org = Organization::current();

        $clientSpaces = $org
            ? HrOrganizationalUnit::where('organization_id', $org->id)
                ->clientSpaces()
                ->with([
                    'clientSpace',
                    'parent',
                    'tierLevel',
                    'routingParents' => fn ($query) => $query
                        ->with('tierLevel')
                        ->orderByDesc('hr_client_space_routes.is_primary')
                        ->orderBy('hr_organizational_units.name'),
                    'staffAssignments' => fn ($query) => $query
                        ->where('status', 'active')
                        ->orderBy('staff_name'),
                    'secondaryStaffAssignments.staffAssignment' => fn ($query) => $query
                        ->orderBy('staff_name'),
                ])
                ->withCount([
                    'staffAssignments as active_staff_count' => fn ($query) => $query->where('status', 'active'),
                    'secondaryStaffAssignments as secondary_staff_count',
                ])
                ->orderBy('name')
                ->get()
                ->each(function (HrOrganizationalUnit $clientSpace): void {
                    $clientSpace->setAttribute('roster_talent_groups', $this->rosterTalentGroupsFor($clientSpace));
                })
            : collect();

        $manageableClientSpaceIds = $clientSpaces
            ->filter(fn (HrOrganizationalUnit $space): bool => $this->canManageClientSpace($space))
            ->pluck('id')
            ->all();

        $this->canManageClientSpaceStaff = ! empty($manageableClientSpaceIds);
        $this->canManageSecondaryAssignments = $this->canManageSecondaryAssignments();
        $this->canManageClientSpacePlacement = $this->canManagePlacement();

        $staffOptions = $org ? $this->availableStaffOptions($org) : collect();
        $secondaryStaffOptions = $org ? $this->availableSecondaryStaffOptions($org) : collect();
        $lowestRoutingUnitOptions = $org && $this->canManageClientSpacePlacement
            ? HrOrganizationalUnit::where('organization_id', $org->id)
                ->lowestRoutingNodes()
                ->with('tierLevel')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'tier_level_id'])
            : collect();
        $routingUnitOptions = $org && $this->canManageClientSpacePlacement
            ? $this->routingUnitOptionsForOrganization($org)
            : collect();
        $primaryRouteIdsInOtherClientSpaces = $org && $this->canManageClientSpacePlacement
            ? $this->primaryRouteIdsInOtherClientSpaces($org)
            : collect();

        return view('livewire.client-space-directory', compact('clientSpaces', 'staffOptions', 'secondaryStaffOptions', 'manageableClientSpaceIds', 'lowestRoutingUnitOptions', 'routingUnitOptions', 'primaryRouteIdsInOtherClientSpaces'));
    }

    private function setPermissions(): void
    {
        $user = Auth::user();

        $this->canManageClientSpaceStaff = $user?->is_hr_admin ?? false;
        $this->canManageSecondaryAssignments = $this->canManageSecondaryAssignments();
        $this->canManageClientSpacePlacement = $this->canManagePlacement();
    }

    private function availableStaffOptions(Organization $org): Collection
    {
        $clientSpace = $this->selectedClientSpace($org);
        if (! $clientSpace) {
            return collect();
        }

        $activeLinkedAssignmentIds = HrClientSpaceStaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('client_space_unit_id', $clientSpace->id)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->pluck('staff_assignment_id')
            ->map(fn ($id): int => (int) $id);

        if (! $clientSpace->parent_id) {
            return collect();
        }

        $primaryRouteId = (int) $clientSpace->parent_id;

        return StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->where('organizational_unit_id', $primaryRouteId)
            ->whereNotIn('id', $activeLinkedAssignmentIds)
            ->orderBy('staff_name')
            ->get()
            ->reject(fn (StaffAssignment $assignment): bool => $this->selectedClientSpaceId
                && (int) $assignment->organizational_unit_id === (int) $this->selectedClientSpaceId)
            ->mapWithKeys(function (StaffAssignment $assignment): array {
                $location = $assignment->organizationalUnit?->name ?? 'Unassigned';
                $title = $this->staffTitleForClientSpace($assignment);
                $department = filled($assignment->staff_department) ? $assignment->staff_department : 'Department not set';
                $details = collect(["Title: {$title}", "Department: {$department}", "Location: {$location}"])
                    ->filter()
                    ->implode(' - ');

                return [
                    $assignment->id => "{$assignment->staff_name} ({$details}, {$assignment->status})",
                ];
            });
    }

    private function rosterTalentGroupsFor(HrOrganizationalUnit $clientSpace): array
    {
        $talent = collect();

        foreach ($clientSpace->staffAssignments as $assignment) {
            $talent->push($this->rosterTalentEntry($assignment, 'Primary'));
        }

        foreach ($clientSpace->secondaryStaffAssignments as $mapping) {
            if ($mapping->staffAssignment) {
                $talent->push($this->rosterTalentEntry($mapping->staffAssignment, 'Additional'));
            }
        }

        return $talent
            ->sortBy([
                ['title', 'asc'],
                ['department', 'asc'],
                ['staff_name', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('title')
            ->map(function (Collection $titleEntries, string $title): array {
                return [
                    'title' => $title,
                    'departments' => $titleEntries
                        ->groupBy('department')
                        ->map(function (Collection $departmentEntries, string $department): array {
                            return [
                                'department' => $department,
                                'staff' => $departmentEntries->values()->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function rosterTalentEntry(StaffAssignment $assignment, string $assignmentScope): array
    {
        return [
            'assignment_id' => $assignment->id,
            'staff_name' => $assignment->staff_name,
            'staff_title' => $assignment->staff_title,
            'title' => $this->staffTitleForClientSpace($assignment),
            'department' => filled($assignment->staff_department) ? $assignment->staff_department : 'Unspecified department',
            'assignment_scope' => $assignmentScope,
        ];
    }

    private function staffTitleForClientSpace(StaffAssignment $assignment): string
    {
        return filled($assignment->staff_title) ? $assignment->staff_title : 'Title not set';
    }

    private function canManagePlacement(?HrOrganizationalUnit $clientSpace = null): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_hr_admin || $user?->canAddHrSetup() || $user?->canEditHrSetup());
    }

    private function availableSecondaryStaffOptions(Organization $org): Collection
    {
        $clientSpace = $this->selectedClientSpace($org);
        if (! $clientSpace) {
            return collect();
        }

        $activeLinkedAssignmentIds = HrClientSpaceStaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('client_space_unit_id', $clientSpace->id)
            ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
            ->pluck('staff_assignment_id')
            ->map(fn ($id): int => (int) $id);

        $primaryRouteId = $clientSpace->parent_id ? (int) $clientSpace->parent_id : null;

        return StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->whereNotIn('id', $activeLinkedAssignmentIds)
            ->where(function ($query) use ($clientSpace): void {
                $query
                    ->whereNull('organizational_unit_id')
                    ->orWhere('organizational_unit_id', '!=', $clientSpace->id);
            })
            ->when($primaryRouteId !== null, fn ($query) => $query->where(function ($inner) use ($primaryRouteId): void {
                $inner
                    ->whereNull('organizational_unit_id')
                    ->orWhere('organizational_unit_id', '!=', $primaryRouteId);
            }))
            ->orderBy('staff_name')
            ->get()
            ->mapWithKeys(function (StaffAssignment $assignment): array {
                $location = $assignment->organizationalUnit?->name ?? 'Unassigned';
                $department = filled($assignment->staff_department) ? $assignment->staff_department : 'Department not set';
                $details = collect(["Title: {$this->staffTitleForClientSpace($assignment)}", "Department: {$department}"])
                    ->filter()
                    ->implode(' - ');
                $name = $details ? "{$assignment->staff_name} ({$details})" : $assignment->staff_name;

                return [
                    $assignment->id => "{$name} ({$location}, {$assignment->status})",
                ];
            });
    }

    private function canManageSecondaryAssignments(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_hr_admin || $user?->canAddHrStaff() || $user?->canEditHrStaff());
    }

    private function resetPlacementModal(): void
    {
        $this->showPlacementModal = false;
        $this->selectedPlacementClientSpaceId = null;
        $this->primaryRoutingUnitId = null;
        $this->linkedRoutingUnitIds = [];
        $this->routingUnitSearch = '';
    }

    private function routingUnitOptionsForOrganization(Organization $org): Collection
    {
        $routingUnitOptions = HrOrganizationalUnit::where('organization_id', $org->id)
            ->lowestRoutingNodes()
            ->with('tierLevel')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'tier_level_id']);

        $search = Str::lower(trim($this->routingUnitSearch));
        if ($search === '') {
            return $routingUnitOptions;
        }

        return $routingUnitOptions
            ->filter(function (HrOrganizationalUnit $routingUnit) use ($search): bool {
                $tierName = $routingUnit->tierLevel?->name ?? '';

                return Str::contains(Str::lower($routingUnit->name), $search)
                    || Str::contains(Str::lower($routingUnit->type ?? ''), $search)
                    || Str::contains(Str::lower($tierName), $search);
            })
            ->values();
    }

    private function lowestRoutingUnitIdsForPlacement(Organization $org): Collection
    {
        return HrOrganizationalUnit::where('organization_id', $org->id)
            ->lowestRoutingNodes()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    private function normalizeLinkedRoutingUnitIds($routingUnitIds, ?Collection $allowedRoutingUnitIds = null): array
    {
        $normalizedRoutingUnitIds = collect($routingUnitIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($allowedRoutingUnitIds) {
            $normalizedRoutingUnitIds = $normalizedRoutingUnitIds
                ->intersect($allowedRoutingUnitIds)
                ->values();
        }

        return $normalizedRoutingUnitIds->all();
    }

    private function primaryRouteIdsInOtherClientSpaces(Organization $org): Collection
    {
        return HrClientSpaceRoute::query()
            ->where('organization_id', $org->id)
            ->where('is_primary', true)
            ->when(
                $this->selectedPlacementClientSpaceId,
                fn ($query) => $query->where('client_space_unit_id', '!=', $this->selectedPlacementClientSpaceId)
            )
            ->pluck('routing_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function selectedClientSpace(Organization $org): ?HrOrganizationalUnit
    {
        if (! $this->selectedClientSpaceId) {
            return null;
        }

        return HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->find($this->selectedClientSpaceId);
    }

    private function attachedRoutingUnitIdsForClientSpace(HrOrganizationalUnit $clientSpace): Collection
    {
        $routeIds = collect();

        if ($clientSpace->parent_id) {
            $routeIds->push((int) $clientSpace->parent_id);
        }

        $routingParents = $clientSpace->relationLoaded('routingParents')
            ? $clientSpace->routingParents
            : $clientSpace->routingParents()->get(['hr_organizational_units.id']);

        return $routeIds
            ->merge($routingParents->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
