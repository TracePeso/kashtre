<?php

namespace App\Livewire;

use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\HrStaffRoutingEvent;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\CascadeRoutingService;
use App\Services\KashApiService;
use App\Support\StaffRecordData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TierStaffAssignmentManager extends Component
{
    public ?int $selectedTierId = null;
    public string $staffUuid = '';
    public $staffTargetTierId = null;
    public string $routingStaffUuid = '';
    public $newSubtierTierLevelId = null;
    public ?string $message = null;
    public bool $canAssignStaff = false;
    public bool $canManageSubtiers = false;
    public bool $canSeedTier = false;
    public array $expandedTierIds = [];

    public function mount(): void
    {
        $this->canAssignStaff = false;
    }

    public function selectTier(int $tierId): void
    {
        $org = $this->currentOrganization();
        abort_unless($org, 404);

        $tier = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->findOrFail($tierId);

        // Auto-expand all ancestors so the selected node is visible in the sidebar.
        $parentId = $tier->parent_id;
        while ($parentId) {
            if (! in_array($parentId, $this->expandedTierIds)) {
                $this->expandedTierIds[] = $parentId;
            }
            $parent = HrOrganizationalUnit::query()->whereKey($parentId)->first(['id', 'parent_id']);
            $parentId = $parent?->parent_id;
        }

        $this->selectedTierId = $tierId;
        $this->staffUuid = '';
        $this->staffTargetTierId = $this->defaultStaffTargetTierId($tier);
        $this->routingStaffUuid = '';
        $this->newSubtierTierLevelId = $this->defaultSubtierTierLevelId($tier);
        $this->message = null;
    }

    public function toggleTierExpansion(int $tierId): void
    {
        if (in_array($tierId, $this->expandedTierIds)) {
            $this->expandedTierIds = array_values(
                array_filter($this->expandedTierIds, fn ($id) => $id !== $tierId)
            );
        } else {
            $this->expandedTierIds[] = $tierId;
        }
    }

    public function createSubtier()
    {
        $org = $this->currentOrganization();
        abort_unless($org, 404);

        $this->validate([
            'selectedTierId' => 'required|integer|exists:hr_organizational_units,id',
            'newSubtierTierLevelId' => 'required|integer|exists:hr_organization_tier_levels,id',
        ]);

        $parent = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->with('tierLevel')
            ->findOrFail($this->selectedTierId);

        if (! $this->canManageSubtiersForUnit($parent)) {
            $this->addError('newSubtierTierLevelId', $this->manageSubtiersDeniedMessage($parent));

            return;
        }

        $tierLevel = HrOrganizationTierLevel::where('organization_id', $org->id)
            ->whereKey($this->newSubtierTierLevelId)
            ->first();

        if (! $tierLevel) {
            $this->addError('newSubtierTierLevelId', 'Choose a tier level from this organization.');

            return;
        }

        if (! $this->subtierLevelIsBelowParent($parent, $tierLevel)) {
            $this->addError('newSubtierTierLevelId', 'Choose a tier level below the selected node.');

            return;
        }

        $name = trim($tierLevel->name);

        if ($this->subtierNameExists($parent, $tierLevel, $name)) {
            $this->addError('newSubtierTierLevelId', 'This routing level already exists under the selected node.');

            return;
        }

        $subtier = HrOrganizationalUnit::create([
            'organization_id' => $org->id,
            'parent_id' => $parent->id,
            'tier_level_id' => $tierLevel->id,
            'name' => $name,
            'type' => $tierLevel->name,
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $this->newSubtierTierLevelId = $this->defaultSubtierTierLevelId($parent);
        $this->staffTargetTierId = $subtier->id;
        $this->message = "Routing node {$name} added under {$this->routingUnitDisplayName($parent)}.";

        if ($this->subtierLevelOptionsFor($subtier)->isEmpty() && $this->userCanManageHrSetup()) {
            session()->flash(
                'status',
                "Routing node {$name} is now the final node. Attach client spaces next, then link staff to those spaces."
            );

            return redirect()->route('hr.organizational-structure.index', [
                'prompt_leaf_unit' => $subtier->id,
                'prompt_leaf_action' => 'client-spaces',
            ]);
        }
    }

    public function assignStaff(CascadeRoutingService $routingService): void
    {
        $org = $this->currentOrganization();
        abort_unless($org, 404);

        $this->validate([
            'selectedTierId' => 'required|integer|exists:hr_organizational_units,id',
            'staffUuid' => 'required|string',
            'staffTargetTierId' => 'nullable|integer|exists:hr_organizational_units,id',
        ]);

        $selectedTier = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->with(['parent', 'tierLevel'])
            ->findOrFail($this->selectedTierId);
        $targetUnit = $this->staffAssignmentTargetFor($selectedTier);

        if (! $targetUnit) {
            return;
        }

        $user = Auth::user();
        abort_unless($user, 403);

        if (! $this->canAssignStaffToUnit($targetUnit)) {
            $this->addError('staffUuid', $this->assignStaffDeniedMessage($targetUnit));

            return;
        }

        $staffDirectory = $this->staffDirectory();
        $staff = $staffDirectory[$this->staffUuid] ?? null;

        if (! $staff) {
            $this->addError('staffUuid', 'Choose a staff member from the available staff list.');

            return;
        }

        if ($reason = $this->staffRouteBlockReason($this->staffUuid, $targetUnit)) {
            $this->addError('staffUuid', $reason);

            return;
        }

        $assignment = StaffAssignment::with('organizationalUnit')->firstOrNew([
            'organization_id' => $org->id,
            'staff_uuid' => $this->staffUuid,
        ]);

        if ($assignment->exists && $assignment->status === 'inactive') {
            $this->addError('staffUuid', 'Inactive staff cannot be routed.');

            return;
        }

        if ((int) $assignment->organizational_unit_id === (int) $targetUnit->id) {
            $this->addError('staffUuid', 'This staff member is already assigned to this destination.');

            return;
        }

        if (! $assignment->exists) {
            $assignment->fill([
                'staff_name' => $staff['name'] ?? $this->staffUuid,
                'staff_cadre' => $staff['cadre'] ?? null,
                'staff_department' => $staff['department'] ?? null,
                'staff_title' => $staff['title'] ?? null,
                'home_branch_external_id' => $staff['home_branch_external_id'] ?? null,
                'home_branch_name' => $staff['home_branch_name'] ?? null,
                'assignment_type' => 'primary',
                'status' => 'active',
                'assigned_at' => now(),
            ])->save();
            $assignment->refresh()->loadMissing('organizationalUnit');
        } elseif ($staff) {
            $assignment->fill([
                'staff_name' => $staff['name'] ?? $assignment->staff_name,
                'staff_cadre' => $staff['cadre'] ?? $assignment->staff_cadre,
                'staff_department' => $staff['department'] ?? $assignment->staff_department,
                'staff_title' => $staff['title'] ?? $assignment->staff_title,
                'home_branch_external_id' => $staff['home_branch_external_id'] ?? $assignment->home_branch_external_id,
                'home_branch_name' => $staff['home_branch_name'] ?? $assignment->home_branch_name,
            ])->save();
        }

        try {
            $routingService->routeStaff($assignment, $targetUnit, $user);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError('staffUuid', $message);
                }
            }

            return;
        }

        $this->staffUuid = '';
        $this->message = "Staff assigned to {$this->routingUnitDisplayName($targetUnit)}.";
    }

    public function assignRoutingStaff(CascadeRoutingService $routingService): void
    {
        $org = $this->currentOrganization();
        abort_unless($org, 404);

        $this->validate([
            'selectedTierId' => 'required|integer|exists:hr_organizational_units,id',
            'routingStaffUuid' => 'required|string',
        ]);

        $tierUnit = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->with(['parent', 'tierLevel'])
            ->findOrFail($this->selectedTierId);

        $user = Auth::user();
        abort_unless($user, 403);

        if (! $this->canSeedTierForUnit($tierUnit)) {
            $this->addError('routingStaffUuid', $this->seedTierDeniedMessage($tierUnit));

            return;
        }

        $staffDirectory = $this->staffDirectory();
        $staff = $staffDirectory[$this->routingStaffUuid] ?? null;

        if (! $staff) {
            $this->addError('routingStaffUuid', 'Choose a staff member from the available staff list.');

            return;
        }

        if ($reason = $this->routingStaffSeedBlockReason($this->routingStaffUuid, $tierUnit)) {
            $this->addError('routingStaffUuid', $reason);

            return;
        }

        $assignment = StaffAssignment::with('organizationalUnit')->firstOrNew([
            'organization_id' => $org->id,
            'staff_uuid' => $this->routingStaffUuid,
        ]);

        if ($assignment->exists && $assignment->status === 'inactive') {
            $this->addError('routingStaffUuid', 'Inactive staff cannot be added as routing staff.');

            return;
        }

        if ($assignment->exists && $assignment->organizational_unit_id !== null) {
            $currentUnit = $assignment->organizationalUnit;
            $this->addError('routingStaffUuid', $currentUnit
                ? "This staff member has already been routed to {$this->routingUnitDisplayName($currentUnit)}."
                : 'This staff member has already been routed.');

            return;
        }

        if (! $assignment->exists) {
            $assignment->fill([
                'staff_name' => $staff['name'] ?? $this->routingStaffUuid,
                'staff_cadre' => $staff['cadre'] ?? null,
                'staff_department' => $staff['department'] ?? null,
                'staff_title' => $staff['title'] ?? null,
                'home_branch_external_id' => $staff['home_branch_external_id'] ?? null,
                'home_branch_name' => $staff['home_branch_name'] ?? null,
                'assignment_type' => 'primary',
                'status' => 'active',
                'assigned_at' => now(),
            ])->save();
            $assignment->refresh()->loadMissing('organizationalUnit');
        } elseif ($staff) {
            $assignment->fill([
                'staff_name' => $staff['name'] ?? $assignment->staff_name,
                'staff_cadre' => $staff['cadre'] ?? $assignment->staff_cadre,
                'staff_department' => $staff['department'] ?? $assignment->staff_department,
                'staff_title' => $staff['title'] ?? $assignment->staff_title,
                'home_branch_external_id' => $staff['home_branch_external_id'] ?? $assignment->home_branch_external_id,
                'home_branch_name' => $staff['home_branch_name'] ?? $assignment->home_branch_name,
            ])->save();
        }

        try {
            $routingService->routeStaff($assignment, $tierUnit, $user);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError('routingStaffUuid', $message);
                }
            }

            return;
        }

        $this->routingStaffUuid = '';
        $this->message = "Routing staff added to {$this->routingUnitDisplayName($tierUnit)}.";
    }

    public function unassignStaff(int $assignmentId): void
    {
        $org = $this->currentOrganization();
        abort_unless($org, 404);

        $assignment = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->findOrFail($assignmentId);

        $unit = $assignment->organizationalUnit;

        if (! $unit || ! $unit->isRoutingNode() || ! $this->canRemoveStaffFromUnit($unit)) {
            $this->addError('staffUuid', 'You do not have permission to remove staff from this node.');

            return;
        }

        DB::transaction(function () use ($assignment, $unit): void {
            $user = Auth::user();
            $fromStatus = $assignment->status;
            $routedAt = now();

            $assignment->forceFill([
                'organizational_unit_id' => null,
                'status' => 'orphaned',
                'routed_by_user_id' => $user?->id,
                'routed_by_staff_uuid' => $user?->staff_uuid,
                'routed_at' => $routedAt,
            ])->save();

            HrStaffRoutingEvent::create([
                'staff_assignment_id' => $assignment->id,
                'organization_id' => $assignment->organization_id,
                'from_unit_id' => $unit->id,
                'to_unit_id' => null,
                'routed_by_user_id' => $user?->id,
                'routed_by_staff_uuid' => $user?->staff_uuid,
                'from_status' => $fromStatus,
                'to_status' => 'orphaned',
                'routed_at' => $routedAt,
            ]);
        });

        $this->message = 'Staff removed from node.';
    }

    public function render(): View
    {
        $org = $this->currentOrganization();

        $tierUnits = $org
            ? HrOrganizationalUnit::where('organization_id', $org->id)
                ->routingNodes()
                ->with(['tierLevel', 'parent'])
                ->withCount(['staffAssignments as assigned_staff_count' => fn ($query) => $query->where('status', '!=', 'inactive')])
                ->orderBy('tier_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $parentIdByTierId = $tierUnits->pluck('parent_id', 'id')->all();
        $childTierIdSet = array_flip(array_filter($tierUnits->pluck('parent_id')->all()));

        $depthByTierId = [];
        foreach ($tierUnits as $u) {
            $depth = 0;
            $pid = $u->parent_id;
            while ($pid) {
                $depth++;
                $pid = $parentIdByTierId[$pid] ?? null;
            }
            $depthByTierId[$u->id] = $depth;
        }

        $expandedTierIds = $this->expandedTierIds;

        $tierRows = $tierUnits->map(fn (HrOrganizationalUnit $unit): array => [
            'unit' => $unit,
            'staff_count' => $unit->assigned_staff_count,
            'subtier_count' => $tierUnits->where('parent_id', $unit->id)->count(),
            'can_assign' => $this->canAssignStaffToUnit($unit),
            'manager_note' => $this->managerNoteFor($unit),
            'depth' => $depthByTierId[$unit->id] ?? 0,
            'has_children' => isset($childTierIdSet[$unit->id]),
            'is_expanded' => in_array($unit->id, $expandedTierIds),
            'is_visible' => $this->isTierVisible($unit, $parentIdByTierId, $expandedTierIds),
        ]);

        $selectedTier = $tierUnits->firstWhere('id', $this->selectedTierId);
        $directSubtiers = $selectedTier
            ? $tierUnits
                ->where('parent_id', $selectedTier->id)
                ->sortBy(fn (HrOrganizationalUnit $unit): string => str_pad((string) ($unit->tierLevel?->level_order ?? 999), 4, '0', STR_PAD_LEFT).'|'.strtolower($unit->name))
                ->values()
            : collect();
        $routesToSubtier = $directSubtiers->isNotEmpty();

        if ($selectedTier && $routesToSubtier && ! $directSubtiers->contains('id', (int) $this->staffTargetTierId)) {
            $this->staffTargetTierId = $directSubtiers->first()->id;
        }

        if ($selectedTier && ! $routesToSubtier) {
            $this->staffTargetTierId = null;
        }

        $staffAssignmentTarget = $selectedTier
            ? ($routesToSubtier ? $directSubtiers->firstWhere('id', (int) $this->staffTargetTierId) : $selectedTier)
            : null;

        $this->canAssignStaff = $staffAssignmentTarget ? $this->canAssignStaffToUnit($staffAssignmentTarget) : false;
        $this->canManageSubtiers = $selectedTier ? $this->canManageSubtiersForUnit($selectedTier) : false;
        $this->canSeedTier = $selectedTier ? $this->canSeedTierForUnit($selectedTier) : false;
        $canRemoveStaff = $selectedTier ? $this->canRemoveStaffFromUnit($selectedTier) : false;

        $assignedStaff = $selectedTier
            ? StaffAssignment::where('organization_id', $selectedTier->organization_id)
                ->where('organizational_unit_id', $selectedTier->id)
                ->where('status', '!=', 'inactive')
                ->orderBy('staff_name')
                ->get()
            : collect();

        $subtierLevelOptions = $selectedTier ? $this->subtierLevelOptionsFor($selectedTier) : collect();

        if ($selectedTier && ! $this->newSubtierTierLevelId && $subtierLevelOptions->isNotEmpty()) {
            $this->newSubtierTierLevelId = $subtierLevelOptions->first()->id;
        }

        $staffDirectory = collect($this->staffDirectory());

        $staffRouteEligibility = $this->staffRouteEligibility($staffDirectory, $staffAssignmentTarget);
        $staffOptions = Arr::pull($staffRouteEligibility, 'options');

        $routingStaffSeedEligibility = $this->routingStaffSeedEligibility($staffDirectory, $selectedTier);
        $tierRoutingStaffOptions = Arr::pull($routingStaffSeedEligibility, 'options');

        return view('livewire.tier-staff-assignment-manager', compact(
            'tierRows',
            'tierUnits',
            'selectedTier',
            'assignedStaff',
            'staffOptions',
            'staffRouteEligibility',
            'directSubtiers',
            'subtierLevelOptions',
            'routesToSubtier',
            'tierRoutingStaffOptions',
            'routingStaffSeedEligibility',
            'canRemoveStaff',
        ));
    }

    private function canAssignStaffToUnit(HrOrganizationalUnit $unit): bool
    {
        $user = Auth::user();

        if (! $user || ! $unit->isRoutingNode()) {
            return false;
        }

        if ($this->userCanManageHrStaff()) {
            return true;
        }

        $parent = $this->parentFor($unit);

        return $parent?->hasRoutingMember($user) ?? false;
    }

    private function canRemoveStaffFromUnit(HrOrganizationalUnit $unit): bool
    {
        $user = Auth::user();

        if (! $user || ! $unit->isRoutingNode()) {
            return false;
        }

        if ($this->userCanManageHrStaff() || $unit->hasRoutingMember($user)) {
            return true;
        }

        $parent = $this->parentFor($unit);

        return $parent?->hasRoutingMember($user) ?? false;
    }

    private function managerNoteFor(HrOrganizationalUnit $unit): string
    {
        $parent = $this->parentFor($unit);

        if (! $parent) {
            return 'Root routing node: HR staff editors can start routing staff here.';
        }

        return "Staff assigned to {$this->routingUnitDisplayName($parent)} can route unrouted staff here.";
    }

    private function staffAssignmentTargetFor(HrOrganizationalUnit $selectedTier): ?HrOrganizationalUnit
    {
        $directSubtiers = $this->directSubtiersFor($selectedTier);

        if ($directSubtiers->isEmpty()) {
            return $selectedTier;
        }

        if (! $this->staffTargetTierId) {
            $this->addError('staffTargetTierId', 'Choose the direct routing node where this staff member should go.');

            return null;
        }

        $target = $directSubtiers->firstWhere('id', (int) $this->staffTargetTierId);

        if (! $target) {
            $this->addError('staffTargetTierId', 'Choose a direct routing node under the selected node.');

            return null;
        }

        return $target;
    }

    private function directSubtiersFor(HrOrganizationalUnit $parent)
    {
        return HrOrganizationalUnit::where('organization_id', $parent->organization_id)
            ->routingNodes()
            ->with(['tierLevel', 'parent'])
            ->withCount(['staffAssignments as assigned_staff_count' => fn ($query) => $query->where('status', '!=', 'inactive')])
            ->where('parent_id', $parent->id)
            ->get()
            ->sortBy(fn (HrOrganizationalUnit $unit): string => str_pad((string) ($unit->tierLevel?->level_order ?? 999), 4, '0', STR_PAD_LEFT).'|'.strtolower($unit->name))
            ->values();
    }

    private function canManageSubtiersForUnit(HrOrganizationalUnit $unit): bool
    {
        $user = Auth::user();

        if (! $user || ! $unit->isRoutingNode()) {
            return false;
        }

        return $this->userCanManageHrSetup() || $unit->hasRoutingMember($user);
    }

    private function canSeedTierForUnit(HrOrganizationalUnit $unit): bool
    {
        $user = Auth::user();

        if (! $user || ! $unit->isRoutingNode()) {
            return false;
        }

        if ($this->parentFor($unit)) {
            return false;
        }

        if ($this->userCanManageHrStaff()) {
            return true;
        }

        return false;
    }

    private function subtierLevelOptionsFor(HrOrganizationalUnit $parent)
    {
        $org = $this->currentOrganization();

        if (! $org) {
            return collect();
        }

        $query = HrOrganizationTierLevel::where('organization_id', $org->id)
            ->orderBy('level_order');

        if ($parent->tierLevel) {
            $query->where('level_order', '>', $parent->tierLevel->level_order);
        }

        return $query->get();
    }

    private function defaultSubtierTierLevelId(HrOrganizationalUnit $parent): ?int
    {
        return $this->subtierLevelOptionsFor($parent)->first()?->id;
    }

    private function subtierLevelIsBelowParent(HrOrganizationalUnit $parent, HrOrganizationTierLevel $tierLevel): bool
    {
        return ! $parent->tierLevel || $tierLevel->level_order > $parent->tierLevel->level_order;
    }

    private function subtierNameExists(HrOrganizationalUnit $parent, HrOrganizationTierLevel $tierLevel, string $name): bool
    {
        return HrOrganizationalUnit::where('organization_id', $parent->organization_id)
            ->routingNodes()
            ->where('parent_id', $parent->id)
            ->where('tier_level_id', $tierLevel->id)
            ->where('name', trim($name))
            ->exists();
    }

    private function defaultStaffTargetTierId(HrOrganizationalUnit $selectedTier): ?int
    {
        return $this->directSubtiersFor($selectedTier)->first()?->id;
    }

    private function staffRouteEligibility($staffDirectory, ?HrOrganizationalUnit $targetUnit): array
    {
        $options = [];
        $blockedReasons = [];

        foreach ($staffDirectory as $uuid => $staff) {
            $reason = $this->staffRouteBlockReason((string) $uuid, $targetUnit);

            if ($reason) {
                $blockedReasons[$reason] = ($blockedReasons[$reason] ?? 0) + 1;
                continue;
            }

            $options[$uuid] = $this->staffOptionLabel($staff);
        }

        return [
            'total' => $staffDirectory->count(),
            'eligible_count' => count($options),
            'blocked_reasons' => $blockedReasons,
            'options' => $options,
        ];
    }

    private function routingStaffSeedEligibility($staffDirectory, ?HrOrganizationalUnit $selectedTier): array
    {
        $options = [];
        $blockedReasons = [];

        foreach ($staffDirectory as $uuid => $staff) {
            $reason = $this->routingStaffSeedBlockReason((string) $uuid, $selectedTier);

            if ($reason) {
                $blockedReasons[$reason] = ($blockedReasons[$reason] ?? 0) + 1;
                continue;
            }

            $options[$uuid] = $this->staffOptionLabel($staff);
        }

        return [
            'total' => $staffDirectory->count(),
            'eligible_count' => count($options),
            'blocked_reasons' => $blockedReasons,
            'options' => $options,
        ];
    }

    private function staffRouteBlockReason(string $staffUuid, ?HrOrganizationalUnit $targetUnit): ?string
    {
        if (! $targetUnit) {
            return 'Choose a destination routing node first.';
        }

        if (! $targetUnit->isRoutingNode()) {
            return 'Staff can only be routed to routing nodes from this screen.';
        }

        $org = $this->currentOrganization();

        if (! $org) {
            return 'No organization is selected.';
        }

        $assignment = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->where('staff_uuid', $staffUuid)
            ->first(['id', 'organizational_unit_id', 'status']);

        if (! $assignment) {
            return null;
        }

        if ($assignment->status === 'inactive') {
            return 'Inactive staff cannot be routed.';
        }

        if ($assignment->status === 'orphaned') {
            return 'Already routed before.';
        }

        if (! $assignment->organizational_unit_id) {
            return null;
        }

        $currentUnit = $assignment->organizationalUnit;

        if (! $currentUnit) {
            return 'Current assignment is linked to a missing routing node.';
        }

        return "Already routed to {$this->routingUnitDisplayName($currentUnit)}.";
    }

    private function routingStaffSeedBlockReason(string $staffUuid, ?HrOrganizationalUnit $selectedTier): ?string
    {
        if (! $selectedTier) {
            return 'Choose a routing node first.';
        }

        $org = $this->currentOrganization();

        if (! $org) {
            return 'No organization is selected.';
        }

        $assignment = StaffAssignment::with('organizationalUnit')
            ->where('organization_id', $org->id)
            ->where('staff_uuid', $staffUuid)
            ->first(['id', 'organizational_unit_id', 'status']);

        if ($assignment?->status === 'inactive') {
            return 'Inactive staff cannot be added as routing staff.';
        }

        if ($assignment?->status === 'orphaned') {
            return 'Already routed before.';
        }

        if (! $assignment || ! $assignment->organizational_unit_id) {
            return null;
        }

        $currentUnit = $assignment->organizationalUnit;

        return $currentUnit
            ? "Already routed to {$this->routingUnitDisplayName($currentUnit)}."
            : 'Current assignment is linked to a missing routing node.';
    }

    private function manageSubtiersDeniedMessage(HrOrganizationalUnit $unit): string
    {
        return "Only staff assigned to {$this->routingUnitDisplayName($unit)} or users with Add HR Setup/Edit HR Setup can add direct nodes here.";
    }

    private function assignStaffDeniedMessage(HrOrganizationalUnit $targetUnit): string
    {
        $parent = $this->parentFor($targetUnit);

        if (! $parent) {
            return 'Add HR Staff or Edit HR Staff permission is required to add staff to a root node.';
        }

        return "Only staff assigned to {$this->routingUnitDisplayName($parent)} or users with Add HR Staff/Edit HR Staff can route staff to {$this->routingUnitDisplayName($targetUnit)}.";
    }

    private function seedTierDeniedMessage(HrOrganizationalUnit $unit): string
    {
        $parent = $this->parentFor($unit);

        if (! $parent) {
            return 'Add HR Staff or Edit HR Staff permission is required to seed routing staff into a root node.';
        }

        return 'Routing staff can only be seeded at the root node. Use the parent node to route staff into child nodes.';
    }

    private function userCanManageHrStaff(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->canAddHrStaff() || $user?->canEditHrStaff());
    }

    private function userCanManageHrSetup(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->canAddHrSetup() || $user?->canEditHrSetup());
    }

    private function parentFor(HrOrganizationalUnit $unit): ?HrOrganizationalUnit
    {
        return $unit->relationLoaded('parent') ? $unit->parent : $unit->parent()->first();
    }

    private function routingUnitDisplayName(HrOrganizationalUnit $unit): string
    {
        if (! $unit->parent_id) {
            $tierLevel = $unit->relationLoaded('tierLevel') ? $unit->tierLevel : $unit->tierLevel()->first();

            return $tierLevel?->name ?? $unit->name;
        }

        return $unit->name;
    }

    private function currentOrganization(): ?Organization
    {
        return Organization::current(Auth::user());
    }

    private function isTierVisible(HrOrganizationalUnit $unit, array $parentIdByTierId, array $expandedTierIds): bool
    {
        $parentId = $unit->parent_id;
        while ($parentId) {
            if (! in_array($parentId, $expandedTierIds)) {
                return false;
            }
            $parentId = $parentIdByTierId[$parentId] ?? null;
        }

        return true;
    }

    private function staffOptionLabel(array $staff): string
    {
        $details = collect([$staff['title'] ?? null, $staff['department'] ?? null])
            ->filter()
            ->implode(' - ');

        return $details ? "{$staff['name']} ({$details})" : $staff['name'];
    }

    private function staffDirectory(): array
    {
        $org = $this->currentOrganization();
        $directory = [];

        if ($org) {
            StaffAssignment::where('organization_id', $org->id)
                ->where('status', '!=', 'inactive')
                ->orderBy('staff_name')
                ->get()
                ->each(function (StaffAssignment $assignment) use (&$directory): void {
                    $directory[$assignment->staff_uuid] = [
                        'name' => $assignment->staff_name,
                        'cadre' => $assignment->staff_cadre,
                        'department' => $assignment->staff_department,
                        'title' => $assignment->staff_title,
                        'home_branch_external_id' => $assignment->home_branch_external_id,
                        'home_branch_name' => $assignment->home_branch_name,
                    ];
                });
        }

        if (! $org) {
            return $directory;
        }

        try {
            $staffData = app(KashApiService::class)->getStaff($this->staffApiParamsFor($org));
        } catch (\Throwable) {
            return $directory;
        }

        foreach (Arr::get($staffData, 'data', []) as $staff) {
            if (! is_array($staff) || ! $this->staffRecordBelongsToOrganization($staff, $org)) {
                continue;
            }

            $uuid = StaffRecordData::uuid($staff);
            $name = StaffRecordData::name($staff);

            if (! $uuid || ! $name) {
                continue;
            }

            $existing = $directory[(string) $uuid] ?? [];

            $directory[(string) $uuid] = [
                'name' => $existing['name'] ?? $name,
                'cadre' => $existing['cadre'] ?? StaffRecordData::cadre($staff),
                'department' => $existing['department'] ?? StaffRecordData::department($staff),
                'title' => $existing['title'] ?? StaffRecordData::title($staff),
                'home_branch_external_id' => $existing['home_branch_external_id'] ?? StaffRecordData::branchExternalId($staff) ?? '',
                'home_branch_name' => $existing['home_branch_name'] ?? StaffRecordData::branchName($staff),
            ];
        }

        return $directory;
    }

    private function staffApiParamsFor(Organization $organization): array
    {
        $params = ['per_page' => 100];

        if (filled($organization->external_business_uuid)) {
            $params['business_id'] = $organization->external_business_uuid;
        }

        return $params;
    }

    private function staffRecordBelongsToOrganization(array $staff, Organization $organization): bool
    {
        $organizationReferences = $this->organizationReferences($organization);

        if (empty($organizationReferences)) {
            return false;
        }

        return collect($this->businessReferencesFromStaffRecord($staff))
            ->intersect($organizationReferences)
            ->isNotEmpty();
    }

    private function organizationReferences(Organization $organization): array
    {
        return collect([$organization->external_business_uuid, $organization->uuid])
            ->filter(fn ($reference): bool => filled($reference))
            ->map(fn ($reference): string => (string) $reference)
            ->unique()
            ->values()
            ->all();
    }

    private function businessReferencesFromStaffRecord(array $staff): array
    {
        $references = [];

        foreach (['business_id', 'business_uuid', 'organization_id', 'organisation_id', 'organization_uuid', 'organisation_uuid'] as $key) {
            if (filled($staff[$key] ?? null)) {
                $references[] = (string) $staff[$key];
            }
        }

        foreach (['business', 'organization', 'organisation'] as $relation) {
            $record = $staff[$relation] ?? null;

            if (! is_array($record)) {
                continue;
            }

            foreach (['id', 'uuid', 'external_business_uuid'] as $key) {
                if (filled($record[$key] ?? null)) {
                    $references[] = (string) $record[$key];
                }
            }
        }

        return collect($references)->unique()->values()->all();
    }
}
