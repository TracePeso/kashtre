<?php

namespace App\Livewire;

use App\Models\HrClientSpaceStaffAssignment;
use App\Models\HrClientSpaceRoute;
use App\Models\HrOrganizationalUnit;
use App\Models\HrOrganizationTierLevel;
use App\Models\Organization;
use App\Models\StaffAssignment;
use App\Services\ClientSpaceRoutingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class OrganizationalStructure extends Component
{
    public $newTierName = '';
    public $newTierOrder = '';
    public $newUnitTierLevelId = null;
    public $newUnitParentId = null;
    public $showModal = false;
    public $editingUnitId = null;
    public $editUnitTierLevelId = null;
    public $editUnitParentId = null;
    public array $blockedParentIds = [];
    public bool $showEditModal = false;
    public bool $canEditRouting = false;
    public ?int $editingTierId = null;
    public $editTierName = '';
    public $editTierOrder = '';
    public bool $showEditTierModal = false;
    public bool $showLeafClientSpacesModal = false;
    public ?int $selectedLeafUnitId = null;
    public array $selectedLeafClientSpaceIds = [];
    public bool $showLeafStaffModal = false;
    public ?int $selectedLeafStaffUnitId = null;
    public ?int $selectedLeafTargetClientSpaceId = null;
    public array $selectedLeafStaffAssignmentIds = [];
    public bool $canManageLeafClientSpaceStaff = false;
    public bool $autoPromptLeafClientSpaces = false;
    public bool $autoPromptLeafStaffAssignments = false;

    protected $rules = [
        'newUnitTierLevelId' => 'required|exists:hr_organization_tier_levels,id',
        'newUnitParentId' => 'nullable|exists:hr_organizational_units,id',
        'newTierName' => 'required|string|max:255',
        'newTierOrder' => 'required|numeric|min:1',
        'editTierName' => 'required|string|max:255',
        'editTierOrder' => 'required|numeric|min:1',
    ];

    public function mount(): void
    {
        $this->canEditRouting = $this->userCanEditRouting();
        $this->canManageLeafClientSpaceStaff = $this->userCanManageLeafClientSpaceStaff();
        $this->applyLeafFlowPromptFromRequest();
    }

    public function openModal($parentId = null): void
    {
        abort_unless($this->canEditRouting, 403);

        $this->resetValidation();
        $this->newUnitParentId = $parentId;
        $this->newUnitTierLevelId = $this->suggestedTierLevelId($parentId);
        $this->showModal = true;
    }

    public function saveTierLevel(): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'newTierName' => 'required|string|max:100',
            'newTierOrder' => 'required|integer|min:1',
        ]);

        $tierLevel = HrOrganizationTierLevel::updateOrCreate(
            [
                'organization_id' => $org->id,
                'level_order' => (int) $this->newTierOrder,
            ],
            [
                'name' => $this->newTierName,
            ]
        );

        $this->syncRootRoutingUnitsForTier($tierLevel);

        $this->newTierName = '';
        $this->newTierOrder = '';

        session()->flash('message', 'Tier level successfully saved.');
    }

    public function openEditTierModal(int $tierId): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $tier = HrOrganizationTierLevel::where('organization_id', $org->id)->findOrFail($tierId);

        $this->resetValidation();
        $this->editingTierId = $tier->id;
        $this->editTierName = $tier->name;
        $this->editTierOrder = $tier->level_order;
        $this->showEditTierModal = true;
    }

    public function updateTierLevel(): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'editTierName' => 'required|string|max:100',
            'editTierOrder' => 'required|integer|min:1',
        ]);

        $tier = HrOrganizationTierLevel::where('organization_id', $org->id)->findOrFail($this->editingTierId);

        $tier->update([
            'name' => $this->editTierName,
            'level_order' => (int) $this->editTierOrder,
        ]);

        $this->syncRootRoutingUnitsForTier($tier);

        $this->showEditTierModal = false;
        $this->editingTierId = null;
        session()->flash('message', 'Tier level successfully updated.');
    }

    public function deleteTierLevel(int $tierId): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $tier = HrOrganizationTierLevel::where('organization_id', $org->id)->findOrFail($tierId);

        $tierUnits = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->where('tier_level_id', $tierId)
            ->get();

        $unitIds = $tierUnits
            ->flatMap(fn (HrOrganizationalUnit $unit): array => array_merge([$unit->id], $this->descendantIds($unit)))
            ->unique()
            ->values();
        $affectedClientSpaceIds = collect();

        DB::transaction(function () use ($tier, $unitIds, &$affectedClientSpaceIds): void {
            if ($unitIds->isNotEmpty()) {
                $affectedClientSpaceIds = HrClientSpaceRoute::whereIn('routing_unit_id', $unitIds)
                    ->pluck('client_space_unit_id')
                    ->unique()
                    ->values();

                HrClientSpaceRoute::whereIn('routing_unit_id', $unitIds)->delete();

                HrOrganizationalUnit::clientSpaces()
                    ->whereIn('parent_id', $unitIds)
                    ->update(['parent_id' => null]);

                StaffAssignment::whereIn('organizational_unit_id', $unitIds)
                    ->update([
                        'organizational_unit_id' => null,
                        'status' => 'orphaned',
                    ]);

                HrOrganizationalUnit::whereIn('id', $unitIds)->delete();
            }

            HrOrganizationalUnit::clientSpaces()
                ->where('tier_level_id', $tier->id)
                ->update([
                    'tier_level_id' => null,
                    'type' => 'Client Space',
                ]);

            $tier->delete();
        });

        app(ClientSpaceRoutingService::class)->promoteRemainingRoutes($affectedClientSpaceIds);

        $deletedCount = $unitIds->count();
        $suffix = $deletedCount > 0 ? " {$deletedCount} routing node(s) under it were also deleted." : '';
        session()->flash('message', "Tier level successfully deleted.{$suffix}");
    }

    public function saveUnit(): void
    {
        abort_unless($this->canEditRouting, 403);

        $this->newUnitParentId = $this->newUnitParentId ?: null;

        $this->validate([
            'newUnitTierLevelId' => 'required|exists:hr_organization_tier_levels,id',
            'newUnitParentId' => 'nullable|exists:hr_organizational_units,id',
        ]);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        if (! $this->validParentFor($org, $this->newUnitParentId)) {
            $this->addError('newUnitParentId', 'Choose a parent tier from the current organization.');

            return;
        }

        $tierLevel = $this->tierLevelFor($org, $this->newUnitTierLevelId);

        if (! $tierLevel) {
            $this->addError('newUnitTierLevelId', 'Choose a tier level from the current organization.');

            return;
        }

        if (! $this->validParentBeforeTier($org, $this->newUnitParentId, $tierLevel)) {
            $this->addError('newUnitParentId', 'Choose a parent from a tier above this node.');

            return;
        }

        if (! $this->parentCanAcceptRoutingChildren($org, $this->newUnitParentId)) {
            $this->addError('newUnitParentId', 'This node is already marked as the last node and cannot have child tiers.');

            return;
        }

        $name = $this->normalizedRoutingNodeName($tierLevel->name);

        if ($this->routingNodeExists($org->id, $this->newUnitParentId, $tierLevel->id, $name)) {
            $this->addError(
                'newUnitTierLevelId',
                $this->newUnitParentId
                    ? 'This routing level already exists under the selected parent.'
                    : 'This root routing level already exists.'
            );

            return;
        }

        HrOrganizationalUnit::create([
            'organization_id' => $org->id,
            'name' => $name,
            'parent_id' => $this->newUnitParentId,
            'tier_level_id' => $tierLevel->id,
            'type' => $tierLevel->name,
            'unit_kind' => HrOrganizationalUnit::KIND_ROUTING_NODE,
        ]);

        $this->showModal = false;
        session()->flash('message', 'Unit successfully added.');
    }

    public function openEditModal(int $unitId): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $unit = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->findOrFail($unitId);

        $this->resetValidation();
        $this->editingUnitId = $unit->id;
        $this->editUnitTierLevelId = $unit->tier_level_id;
        $this->editUnitParentId = $this->validParentFor($org, $unit->parent_id) ? $unit->parent_id : null;
        $this->blockedParentIds = array_merge([$unit->id], $this->descendantIds($unit));
        $this->showEditModal = true;
    }

    public function saveUnitConfiguration(): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $unit = HrOrganizationalUnit::where('organization_id', $org->id)->findOrFail($this->editingUnitId);

        $this->editUnitParentId = $this->editUnitParentId ?: null;

        $this->validate([
            'editUnitTierLevelId' => 'required|exists:hr_organization_tier_levels,id',
            'editUnitParentId' => 'nullable|exists:hr_organizational_units,id',
        ]);

        if (in_array((int) $this->editUnitParentId, $this->blockedParentIds, true)) {
            $this->addError('editUnitParentId', 'A tier cannot be placed under itself or one of its children.');

            return;
        }

        if (! $this->validParentFor($org, $this->editUnitParentId)) {
            $this->addError('editUnitParentId', 'Choose a parent tier from the current organization.');

            return;
        }

        $tierLevel = $this->tierLevelFor($org, $this->editUnitTierLevelId);

        if (! $tierLevel) {
            $this->addError('editUnitTierLevelId', 'Choose a tier level from the current organization.');

            return;
        }

        if (! $this->validParentBeforeTier($org, $this->editUnitParentId, $tierLevel)) {
            $this->addError('editUnitParentId', 'Choose a parent from a tier above this node.');

            return;
        }

        if (! $this->parentCanAcceptRoutingChildren($org, $this->editUnitParentId, $unit->id)) {
            $this->addError('editUnitParentId', 'This node is already marked as the last node and cannot have child tiers.');

            return;
        }

        $name = $this->normalizedRoutingNodeName(
            $this->editUnitParentId ? $unit->name : $tierLevel->name
        );

        if ($this->routingNodeExists($org->id, $this->editUnitParentId, $tierLevel->id, $name, $unit->id)) {
            $this->addError(
                'editUnitTierLevelId',
                $this->editUnitParentId
                    ? 'This routing level already exists under the selected parent.'
                    : 'This root routing level already exists.'
            );

            return;
        }

        $unit->update([
            'name' => $name,
            'parent_id' => $this->editUnitParentId ?: null,
            'tier_level_id' => $tierLevel->id,
            'type' => $tierLevel->name,
        ]);

        $this->showEditModal = false;
        session()->flash('message', 'Routing tier successfully updated.');
    }

    public function openLeafClientSpacesModal(int $unitId): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $leafUnit = $this->routingNodeForOrganization($org, $unitId);
        if (! $leafUnit) {
            abort(404);
        }

        if ($leafUnit->hasRoutingChildren()) {
            session()->flash('error', 'Only a node without child tiers can be marked as the last node.');

            return;
        }

        $markedNow = $this->ensureLastRoutingNodeFlag($leafUnit);

        $this->resetValidation();
        $this->selectedLeafUnitId = $leafUnit->id;
        $this->selectedLeafClientSpaceIds = $leafUnit->linkedClientSpaces()
            ->orderBy('hr_organizational_units.name')
            ->get(['hr_organizational_units.id'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->autoPromptLeafClientSpaces = false;
        $this->autoPromptLeafStaffAssignments = false;
        $this->showLeafClientSpacesModal = true;

        if ($markedNow) {
            session()->flash('status', 'Last node confirmed. Attach client spaces here, then the system will prompt you to link staff immediately.');
        }
    }

    public function openLeafStaffModal(int $unitId, ?int $clientSpaceId = null): void
    {
        abort_unless($this->canManageLeafClientSpaceStaff, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $leafUnit = $this->selectedLeafRoutingUnitForOrganization($org, $unitId);
        if (! $leafUnit) {
            abort(404);
        }

        $attachedClientSpaceIds = $leafUnit->linkedClientSpaces()
            ->orderBy('hr_organizational_units.name')
            ->get(['hr_organizational_units.id'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($attachedClientSpaceIds->isEmpty()) {
            session()->flash('error', 'Attach client spaces to this last routing node before linking staff.');

            return;
        }

        $this->resetValidation();
        $this->selectedLeafUnitId = $leafUnit->id;
        $this->selectedLeafStaffUnitId = $leafUnit->id;
        $this->selectedLeafTargetClientSpaceId = $clientSpaceId && $attachedClientSpaceIds->contains((int) $clientSpaceId)
            ? (int) $clientSpaceId
            : (int) $attachedClientSpaceIds->first();
        $this->selectedLeafStaffAssignmentIds = [];
        $this->autoPromptLeafClientSpaces = false;
        $this->autoPromptLeafStaffAssignments = false;
        $this->showLeafStaffModal = true;
    }

    public function saveLeafClientSpaces(ClientSpaceRoutingService $clientSpaceRoutingService): void
    {
        abort_unless($this->canEditRouting, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'selectedLeafUnitId' => 'required|integer|exists:hr_organizational_units,id',
            'selectedLeafClientSpaceIds' => 'array',
            'selectedLeafClientSpaceIds.*' => 'integer|exists:hr_organizational_units,id',
        ]);

        $leafUnit = $this->selectedLeafRoutingUnitForOrganization($org, (int) $this->selectedLeafUnitId);
        if (! $leafUnit) {
            $this->addError('selectedLeafUnitId', 'Choose a valid last routing node.');

            return;
        }

        $selectedClientSpaceIds = collect($this->selectedLeafClientSpaceIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $clientSpaces = HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->whereIn('id', $selectedClientSpaceIds)
            ->get()
            ->keyBy('id');

        if ($clientSpaces->count() !== $selectedClientSpaceIds->count()) {
            $this->addError('selectedLeafClientSpaceIds', 'Choose client spaces from the current organization.');

            return;
        }

        $currentClientSpaceIds = $leafUnit->linkedClientSpaces()
            ->get(['hr_organizational_units.id'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $allClientSpaceIds = $currentClientSpaceIds
            ->merge($selectedClientSpaceIds)
            ->unique()
            ->values();

        foreach ($allClientSpaceIds as $clientSpaceId) {
            $clientSpace = $clientSpaces->get($clientSpaceId)
                ?? HrOrganizationalUnit::where('organization_id', $org->id)
                    ->clientSpaces()
                    ->with(['parent', 'routingParents'])
                    ->find($clientSpaceId);

            if (! $clientSpace) {
                continue;
            }

            $existingRouteIds = $this->attachedRoutingUnitIdsForClientSpace($clientSpace);

            if ($selectedClientSpaceIds->contains($clientSpaceId)) {
                $routeIds = $existingRouteIds
                    ->push($leafUnit->id)
                    ->unique()
                    ->values()
                    ->all();
                $primaryRoutingUnitId = $leafUnit->id;
                $detachLeafStaffLinks = false;
            } else {
                $routeIds = $existingRouteIds
                    ->reject(fn (int $routeId): bool => $routeId === (int) $leafUnit->id)
                    ->values()
                    ->all();
                $primaryRoutingUnitId = in_array((int) $clientSpace->parent_id, $routeIds, true)
                    ? (int) $clientSpace->parent_id
                    : ($routeIds[0] ?? null);
                $detachLeafStaffLinks = true;
            }

            try {
                $clientSpaceRoutingService->syncRoutes($clientSpace, $routeIds, $primaryRoutingUnitId);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $messages) {
                    foreach ((array) $messages as $message) {
                        $this->addError('selectedLeafClientSpaceIds', $message);
                    }
                }

                return;
            }

            if ($detachLeafStaffLinks) {
                HrClientSpaceStaffAssignment::query()
                    ->where('organization_id', $org->id)
                    ->where('client_space_unit_id', (int) $clientSpaceId)
                    ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                    ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
                    ->whereHas('staffAssignment', function ($query) use ($leafUnit): void {
                        $query->where('organizational_unit_id', $leafUnit->id);
                    })
                    ->update([
                        'status' => HrClientSpaceStaffAssignment::STATUS_INACTIVE,
                        'notes' => 'Detached because the client space was removed from the last routing node',
                    ]);
            }
        }

        $selectedClientSpaceIdsForStaffPrompt = $selectedClientSpaceIds
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $leafUnitId = (int) $leafUnit->id;

        if (
            $selectedClientSpaceIdsForStaffPrompt !== []
            && $this->canManageLeafClientSpaceStaff
        ) {
            $this->showLeafClientSpacesModal = false;
            $this->selectedLeafClientSpaceIds = [];
            $this->openLeafStaffPrompt($leafUnitId, $selectedClientSpaceIdsForStaffPrompt);
            session()->flash('status', 'Client spaces saved. Choose a client space, then link last-node staff to it.');

            return;
        }

        $this->resetLeafClientSpacesModal();
        session()->flash('message', 'Client spaces attached to the last routing node.');
    }

    public function assignLeafStaffToClientSpaces(): void
    {
        abort_unless($this->canManageLeafClientSpaceStaff, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $this->validate([
            'selectedLeafStaffUnitId' => 'required|integer|exists:hr_organizational_units,id',
            'selectedLeafTargetClientSpaceId' => 'required|integer|exists:hr_organizational_units,id',
            'selectedLeafStaffAssignmentIds' => 'required|array|min:1',
            'selectedLeafStaffAssignmentIds.*' => 'integer|distinct|exists:hr_staff_assignments,id',
        ]);

        $leafUnit = $this->lowestRoutingUnitForOrganization($org, $this->activeLeafStaffUnitId() ?? 0);
        if (! $leafUnit) {
            $this->addError('selectedLeafStaffUnitId', 'Choose a valid last routing node.');

            return;
        }

        $targetClientSpace = $this->attachedClientSpacesForSelectedLeaf($org)
            ->first(fn (HrOrganizationalUnit $clientSpace): bool => (int) $clientSpace->id === (int) $this->selectedLeafTargetClientSpaceId);

        if (! $targetClientSpace) {
            $this->addError('selectedLeafTargetClientSpaceId', 'Choose an attached client space from this last routing node.');

            return;
        }

        $staffAssignments = StaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('organizational_unit_id', $leafUnit->id)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->whereIn('id', collect($this->selectedLeafStaffAssignmentIds)->map(fn ($id): int => (int) $id)->unique())
            ->get()
            ->keyBy('id');

        if ($staffAssignments->count() !== count(array_unique(array_map('intval', $this->selectedLeafStaffAssignmentIds)))) {
            $this->addError('selectedLeafStaffAssignmentIds', 'Choose staff who are currently assigned to this last routing node.');

            return;
        }

        DB::transaction(function () use ($org, $targetClientSpace, $staffAssignments): void {
            foreach ($staffAssignments as $assignment) {
                HrClientSpaceStaffAssignment::updateOrCreate(
                    [
                        'organization_id' => $org->id,
                        'client_space_unit_id' => (int) $targetClientSpace->id,
                        'staff_assignment_id' => (int) $assignment->id,
                        'assignment_type' => HrClientSpaceStaffAssignment::TYPE_SECONDARY,
                    ],
                    [
                        'staff_uuid' => $assignment->staff_uuid,
                        'status' => HrClientSpaceStaffAssignment::STATUS_ACTIVE,
                        'assigned_by_user_id' => Auth::id(),
                        'assigned_at' => now(),
                        'notes' => 'Linked directly from last routing node',
                    ]
                );
            }
        });

        $linkedStaffCount = $staffAssignments->count();

        $this->selectedLeafStaffAssignmentIds = [];
        session()->flash(
            'message',
            "{$linkedStaffCount} last-node staff linked to {$targetClientSpace->name}."
        );
    }

    public function render()
    {
        $this->canEditRouting = $this->userCanEditRouting();
        $this->canManageLeafClientSpaceStaff = $this->userCanManageLeafClientSpaceStaff();

        $org = Organization::current();
        $rootUnits = $org ? HrOrganizationalUnit::where('organization_id', $org->id)
                                                ->routingNodes()
                                                ->whereNull('parent_id')
                                                ->with([
                                                    'childrenRecursive',
                                                    'tierLevel',
                                                    'linkedClientSpaces' => fn ($query) => $query
                                                        ->with('routingParents')
                                                        ->withCount([
                                                            'staffAssignments as active_staff_count' => fn ($staffQuery) => $staffQuery->where('status', 'active'),
                                                        ])
                                                        ->withCount([
                                                            'secondaryStaffAssignments as secondary_staff_count',
                                                        ])
                                                        ->orderByDesc('hr_client_space_routes.is_primary')
                                                        ->orderBy('hr_organizational_units.name'),
                                                ])
                                                ->withCount(['staffAssignments as routing_staff_count' => fn ($query) => $query->whereNotIn('status', ['inactive', 'orphaned'])])
                                                ->get() : collect();
        $parentOptions = $org ? HrOrganizationalUnit::where('organization_id', $org->id)
                                                ->routingNodes()
                                                ->with('tierLevel')
                                                ->orderBy('name')
                                                ->get(['id', 'name', 'type', 'tier_level_id', 'parent_id']) : collect();
        $tierLevels = $org ? HrOrganizationTierLevel::where('organization_id', $org->id)
                                                ->orderBy('level_order')
                                                ->get() : collect();
        $structureSummary = $org ? [
            'routing_nodes' => HrOrganizationalUnit::where('organization_id', $org->id)->routingNodes()->count(),
            'root_nodes' => HrOrganizationalUnit::where('organization_id', $org->id)->routingNodes()->whereNull('parent_id')->count(),
            'staff_pool' => StaffAssignment::where('organization_id', $org->id)->where('status', '!=', 'inactive')->count(),
        ] : [
            'routing_nodes' => 0,
            'root_nodes' => 0,
            'staff_pool' => 0,
        ];
        $clientSpaceOptions = $org ? HrOrganizationalUnit::where('organization_id', $org->id)
            ->clientSpaces()
            ->with(['parent', 'routingParents'])
            ->withCount([
                'staffAssignments as active_staff_count' => fn ($query) => $query->where('status', 'active'),
                'secondaryStaffAssignments as secondary_staff_count',
            ])
            ->orderBy('name')
            ->get() : collect();
        $selectedLeafUnitId = $this->showLeafStaffModal
            ? $this->activeLeafStaffUnitId()
            : $this->selectedLeafUnitId;
        $selectedLeafUnit = $org && $selectedLeafUnitId
            ? $this->selectedLeafRoutingUnitForOrganization($org, (int) $selectedLeafUnitId)
            : null;
        $leafClientSpaceOptions = $org && $selectedLeafUnitId
            ? $this->attachedClientSpacesForSelectedLeaf($org)
            : collect();
        $leafStaffAssignments = $org && $selectedLeafUnitId
            ? $this->availableLeafStaffAssignments($org)
            : collect();
        $canManageLeafClientSpaceStaff = $this->canManageLeafClientSpaceStaff;

        return view('livewire.organizational-structure', compact(
            'rootUnits',
            'parentOptions',
            'tierLevels',
            'structureSummary',
            'clientSpaceOptions',
            'selectedLeafUnit',
            'leafClientSpaceOptions',
            'leafStaffAssignments',
            'canManageLeafClientSpaceStaff',
        ));
    }

    private function userCanEditRouting(): bool
    {
        return (Auth::user()?->canAddHrSetup() ?? false)
            || (Auth::user()?->canEditHrSetup() ?? false);
    }

    private function userCanManageLeafClientSpaceStaff(): bool
    {
        $user = Auth::user();

        return (bool) (
            $user?->is_hr_admin
            || $user?->canAddHrStaff()
            || $user?->canEditHrStaff()
            || $user?->canAddHrSetup()
            || $user?->canEditHrSetup()
        );
    }

    private function validParentFor(Organization $org, mixed $parentId): bool
    {
        if (! $parentId) {
            return true;
        }

        return HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->whereKey($parentId)
            ->exists();
    }

    private function validParentBeforeTier(Organization $org, mixed $parentId, HrOrganizationTierLevel $tierLevel): bool
    {
        if (! $parentId) {
            return true;
        }

        $parent = HrOrganizationalUnit::with('tierLevel')
            ->where('organization_id', $org->id)
            ->routingNodes()
            ->whereKey($parentId)
            ->first();

        return $parent?->tierLevel
            ? $parent->tierLevel->level_order < $tierLevel->level_order
            : true;
    }

    private function parentCanAcceptRoutingChildren(Organization $org, mixed $parentId, ?int $movingUnitId = null): bool
    {
        if (! $parentId) {
            return true;
        }

        $parent = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->whereKey($parentId)
            ->first();

        if (! $parent) {
            return false;
        }

        if ($movingUnitId !== null && (int) $parent->id === (int) $movingUnitId) {
            return true;
        }

        return ! $parent->isLowestRoutingNode();
    }

    private function normalizedRoutingNodeName(?string $name): string
    {
        return trim((string) $name);
    }

    private function routingNodeExists(
        int $organizationId,
        mixed $parentId,
        int $tierLevelId,
        string $name,
        ?int $exceptUnitId = null
    ): bool {
        $query = HrOrganizationalUnit::where('organization_id', $organizationId)
            ->routingNodes()
            ->where('tier_level_id', $tierLevelId)
            ->where('name', $name);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        if ($exceptUnitId) {
            $query->whereKeyNot($exceptUnitId);
        }

        return $query->exists();
    }

    private function tierLevelFor(Organization $org, mixed $tierLevelId): ?HrOrganizationTierLevel
    {
        return $tierLevelId
            ? HrOrganizationTierLevel::where('organization_id', $org->id)->whereKey($tierLevelId)->first()
            : null;
    }

    private function syncRootRoutingUnitsForTier(HrOrganizationTierLevel $tierLevel): void
    {
        HrOrganizationalUnit::where('organization_id', $tierLevel->organization_id)
            ->routingNodes()
            ->where('tier_level_id', $tierLevel->id)
            ->whereNull('parent_id')
            ->update([
                'name' => $tierLevel->name,
                'type' => $tierLevel->name,
            ]);
    }

    private function suggestedTierLevelId(mixed $parentId): ?int
    {
        $org = Organization::current();

        if (! $org) {
            return null;
        }

        if ($parentId) {
            $parent = HrOrganizationalUnit::with('tierLevel')
                ->where('organization_id', $org->id)
                ->routingNodes()
                ->find($parentId);

            if ($parent?->tierLevel) {
                return HrOrganizationTierLevel::where('organization_id', $org->id)
                    ->where('level_order', '>', $parent->tierLevel->level_order)
                    ->orderBy('level_order')
                    ->value('id');
            }
        }

        return HrOrganizationTierLevel::where('organization_id', $org->id)
            ->orderBy('level_order')
            ->value('id');
    }

    private function descendantIds(HrOrganizationalUnit $unit): array
    {
        $ids = [];

        $unit->children()->routingNodes()->get()->each(function (HrOrganizationalUnit $child) use (&$ids): void {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantIds($child));
        });

        return $ids;
    }

    private function lowestRoutingUnitForOrganization(Organization $org, int $unitId): ?HrOrganizationalUnit
    {
        return HrOrganizationalUnit::where('organization_id', $org->id)
            ->lowestRoutingNodes()
            ->with(['tierLevel', 'linkedClientSpaces'])
            ->find($unitId);
    }

    private function selectedLeafRoutingUnitForOrganization(Organization $org, int $unitId): ?HrOrganizationalUnit
    {
        $unit = HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->with(['tierLevel', 'linkedClientSpaces'])
            ->find($unitId);

        if (! $unit || $unit->hasRoutingChildren()) {
            return null;
        }

        return $unit;
    }

    private function routingNodeForOrganization(Organization $org, int $unitId): ?HrOrganizationalUnit
    {
        return HrOrganizationalUnit::where('organization_id', $org->id)
            ->routingNodes()
            ->with(['tierLevel', 'children', 'linkedClientSpaces'])
            ->find($unitId);
    }

    private function ensureLastRoutingNodeFlag(HrOrganizationalUnit $unit): bool
    {
        if (! $unit->isRoutingNode()) {
            return false;
        }

        if ($unit->isMarkedAsLastRoutingNode()) {
            return false;
        }

        $metadata = $unit->metadata ?? [];
        $metadata[HrOrganizationalUnit::METADATA_LAST_ROUTING_NODE] = true;

        $unit->forceFill(['metadata' => $metadata])->save();
        $unit->refresh();

        return true;
    }

    private function attachedClientSpacesForSelectedLeaf(Organization $org)
    {
        $leafUnitId = $this->activeLeafStaffUnitId();
        if (! $leafUnitId) {
            return collect();
        }

        $leafUnit = $this->selectedLeafRoutingUnitForOrganization($org, $leafUnitId);
        if (! $leafUnit) {
            return collect();
        }

        return $leafUnit->linkedClientSpaces()
            ->withCount([
                'staffAssignments as active_staff_count' => fn ($query) => $query->where('status', 'active'),
                'secondaryStaffAssignments as secondary_staff_count',
            ])
            ->orderByDesc('hr_client_space_routes.is_primary')
            ->orderBy('hr_organizational_units.name')
            ->get()
            ->whenEmpty(function ($collection) use ($org) {
                $selectedId = $this->selectedLeafTargetClientSpaceId ? (int) $this->selectedLeafTargetClientSpaceId : null;

                if (! $selectedId) {
                    return $collection;
                }

                return HrOrganizationalUnit::where('organization_id', $org->id)
                    ->clientSpaces()
                    ->whereKey($selectedId)
                    ->withCount([
                        'staffAssignments as active_staff_count' => fn ($query) => $query->where('status', 'active'),
                        'secondaryStaffAssignments as secondary_staff_count',
                    ])
                    ->orderBy('name')
                    ->get();
            });
    }

    private function availableLeafStaffAssignments(Organization $org)
    {
        $leafUnitId = $this->activeLeafStaffUnitId();
        if (! $leafUnitId) {
            return collect();
        }

        return StaffAssignment::query()
            ->where('organization_id', $org->id)
            ->where('organizational_unit_id', $leafUnitId)
            ->whereNotIn('status', ['inactive', 'orphaned'])
            ->with([
                'clientSpaceStaffAssignments' => fn ($query) => $query
                    ->where('assignment_type', HrClientSpaceStaffAssignment::TYPE_SECONDARY)
                    ->where('status', HrClientSpaceStaffAssignment::STATUS_ACTIVE)
                    ->with('clientSpace'),
            ])
            ->orderBy('staff_name')
            ->get();
    }

    private function attachedRoutingUnitIdsForClientSpace(HrOrganizationalUnit $clientSpace)
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

    private function applyLeafFlowPromptFromRequest(): void
    {
        $leafUnitId = request()->integer('prompt_leaf_unit');
        $action = (string) request()->query('prompt_leaf_action', 'client-spaces');

        if (! $leafUnitId) {
            return;
        }

        if ($action === 'client-spaces' && $this->canEditRouting) {
            $this->openLeafClientSpacesModal($leafUnitId);
            $this->autoPromptLeafClientSpaces = true;
            session()->flash('status', 'Final routing node confirmed. Attach client spaces here, then the system will prompt you to link staff immediately.');
        }
    }

    private function openLeafStaffPrompt(int $leafUnitId, array $clientSpaceIds): void
    {
        $this->selectedLeafUnitId = $leafUnitId;
        $this->selectedLeafStaffUnitId = $leafUnitId;
        $clientSpaceIds = array_values(array_unique(array_map('intval', $clientSpaceIds)));
        $this->selectedLeafTargetClientSpaceId = $clientSpaceIds[0] ?? null;
        $this->selectedLeafStaffAssignmentIds = [];
        $this->autoPromptLeafClientSpaces = false;
        $this->autoPromptLeafStaffAssignments = true;
        $this->showLeafStaffModal = true;
    }

    private function activeLeafStaffUnitId(): ?int
    {
        return $this->selectedLeafStaffUnitId ?? $this->selectedLeafUnitId;
    }

    private function resetLeafClientSpacesModal(): void
    {
        $this->showLeafClientSpacesModal = false;
        $this->selectedLeafUnitId = null;
        $this->selectedLeafClientSpaceIds = [];
        $this->autoPromptLeafClientSpaces = false;
    }

    private function resetLeafStaffModal(): void
    {
        $this->showLeafStaffModal = false;
        $this->selectedLeafUnitId = null;
        $this->selectedLeafStaffUnitId = null;
        $this->selectedLeafTargetClientSpaceId = null;
        $this->selectedLeafStaffAssignmentIds = [];
        $this->autoPromptLeafStaffAssignments = false;
    }

}
