<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Client Spaces</h2>
            <p class="mt-1 text-sm text-gray-500">Imported from Main KashTre and linked to one or more local routing paths.</p>
        </div>
        @if(Auth::user()?->canViewHrSetup())
            <a href="{{ route('hr.organizational-structure.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Manage Placement
            </a>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('message') }}
        </div>
    @endif

    @if($clientSpaces->isEmpty())
        <div class="rounded-md border border-dashed border-gray-300 bg-white px-5 py-8 text-center">
            <p class="text-sm font-medium text-gray-900">No client spaces imported yet.</p>
            <p class="mt-1 text-sm text-gray-500">Create client spaces in Main KashTre, then run a KashTre sync.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
            <div class="divide-y divide-gray-100">
                @foreach($clientSpaces as $clientSpace)
                    <div class="px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-gray-900">{{ $clientSpace->name }}</h3>
                                    @if($clientSpace->hasClientSpacePlacement())
                                        <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Placed</span>
                                    @else
                                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Needs placement</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $clientSpace->parent ? 'Primary route: '.$clientSpace->parent->name : 'Not attached to routing yet' }}
                                </p>
                                @php($sharedRoutes = $clientSpace->routingParents->filter(fn ($route) => (int) $route->id !== (int) $clientSpace->parent_id)->values())
                                @if($sharedRoutes->isNotEmpty())
                                    <p class="mt-1 text-xs text-gray-500">
                                        Shared with:
                                        {{ $sharedRoutes->pluck('name')->implode(', ') }}
                                    </p>
                                @endif
                                @if($clientSpace->clientSpace?->last_synced_at)
                                    <p class="mt-1 text-xs text-gray-500">
                                        Synced {{ $clientSpace->clientSpace->last_synced_at->diffForHumans() }}
                                    </p>
                                @endif
                            </div>
                            <div class="shrink-0 rounded-md bg-gray-50 px-3 py-2 text-right">
                                @php($totalStaffCount = (int) $clientSpace->active_staff_count + (int) ($clientSpace->secondary_staff_count ?? 0))
                                <p class="text-xs font-medium text-gray-500">Active staff</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $totalStaffCount }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ (int) $clientSpace->active_staff_count }} primary
                                    @if(($clientSpace->secondary_staff_count ?? 0) > 0)
                                        | {{ (int) $clientSpace->secondary_staff_count }} linked
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-500">{{ $clientSpace->routingParents->count() }} route{{ $clientSpace->routingParents->count() === 1 ? '' : 's' }}</p>
                                @if(in_array($clientSpace->id, $manageableClientSpaceIds, true))
                                    <button type="button" wire:click="openAddStaffModal({{ $clientSpace->id }})" class="mt-2 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        Add Staff
                                    </button>
                                @endif
                                @if($canManageSecondaryAssignments)
                                    <button type="button" wire:click="openSecondaryAssignmentModal({{ $clientSpace->id }})" class="mt-2 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        Secondary Additions
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 border-t border-gray-100 pt-3">
                            <p class="text-xs font-semibold uppercase text-gray-500">Staff grouped by title</p>
                            @php($talentGroups = $clientSpace->roster_talent_groups ?? [])
                            @if(empty($talentGroups))
                                <p class="mt-2 text-sm text-gray-500">No active staff added yet.</p>
                            @else
                                <div class="mt-3 space-y-3">
                                    @foreach($talentGroups as $titleGroup)
                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                            <p class="text-xs font-semibold uppercase text-gray-600">{{ $titleGroup['title'] }}</p>
                                            <div class="mt-2 space-y-2">
                                                @foreach($titleGroup['departments'] as $departmentGroup)
                                                    <div>
                                                        <p class="text-xs font-medium text-gray-500">{{ $departmentGroup['department'] }}</p>
                                                        <div class="mt-1 flex flex-wrap gap-2">
                                                            @foreach($departmentGroup['staff'] as $staffEntry)
                                                                <div class="inline-flex items-center gap-1 rounded {{ $staffEntry['assignment_scope'] === 'Additional' ? 'border border-amber-200 bg-amber-50 text-amber-800' : 'bg-white text-gray-700 border border-gray-200' }} px-2 py-1 text-xs font-medium">
                                                                    <span>{{ $staffEntry['staff_name'] }}</span>
                                                                    @if($staffEntry['assignment_scope'] === 'Additional')
                                                                        <span class="text-amber-600">/ Additional</span>
                                                                    @endif
                                                                    <span class="text-[11px] font-normal {{ $staffEntry['assignment_scope'] === 'Additional' ? 'text-amber-700' : 'text-gray-500' }}">
                                                                        / {{ $staffEntry['shift_preference_summary'] }}
                                                                    </span>
                                                                    @if($canManageShiftPreferences)
                                                                        <button
                                                                            type="button"
                                                                            wire:click="openShiftPreferenceModal({{ $clientSpace->id }}, {{ $staffEntry['assignment_id'] }})"
                                                                            class="ml-1 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                                                        >
                                                                            Shift Preference
                                                                        </button>
                                                                    @endif
                                                                    @if($staffEntry['assignment_scope'] === 'Primary' && in_array($clientSpace->id, $manageableClientSpaceIds, true))
                                                                        <button
                                                                            type="button"
                                                                            wire:click="removePrimaryStaffFromClientSpace({{ $clientSpace->id }}, {{ $staffEntry['assignment_id'] }})"
                                                                            wire:confirm="Remove {{ $staffEntry['staff_name'] }} from {{ $clientSpace->name }}?"
                                                                            class="ml-1 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-gray-600 hover:bg-gray-50"
                                                                        >
                                                                            Remove
                                                                        </button>
                                                                    @elseif($staffEntry['assignment_scope'] === 'Additional' && $canManageSecondaryAssignments)
                                                                        <button
                                                                            type="button"
                                                                            wire:click="removeSecondaryStaffFromClientSpace({{ $clientSpace->id }}, {{ $staffEntry['assignment_id'] }})"
                                                                            wire:confirm="Remove {{ $staffEntry['staff_name'] }} from {{ $clientSpace->name }}?"
                                                                            class="ml-1 rounded border border-amber-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-amber-700 hover:bg-amber-50"
                                                                        >
                                                                            Remove
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($showPlacementModal)
        @php($selectedPlacementClientSpace = $clientSpaces->firstWhere('id', $selectedPlacementClientSpaceId))
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-2xl max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="mb-2 text-lg font-medium leading-6 text-gray-900">
                    Manage Client Space Routes
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Add the lowest routing units this client space can receive staff from, then mark one selected route as the primary route.
                </p>

                <form wire:submit.prevent="savePlacement">
                    <div>
                        @php($lowestRoutingUnitIds = $lowestRoutingUnitOptions->pluck('id')->map(fn ($id) => (int) $id))
                        <label class="block text-sm font-medium text-gray-700">Add Units</label>
                        <div class="mt-1 overflow-hidden rounded-md border border-gray-300">
                            <div class="border-b border-gray-200 px-3 py-2">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="routingUnitSearch"
                                    placeholder="Search units by name, type, or tier"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                            </div>
                            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2">
                                <p class="text-xs text-gray-500">
                                    {{ count($linkedRoutingUnitIds) }} selected
                                </p>
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="selectAllVisibleUnits" class="text-xs font-semibold text-blue-600 hover:text-blue-500">
                                        Select all visible
                                    </button>
                                    <button type="button" wire:click="clearUnits" class="text-xs font-semibold text-gray-600 hover:text-gray-500">
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                @forelse($routingUnitOptions as $routingUnit)
                                    @php($isLowestRoutingUnit = $lowestRoutingUnitIds->contains((int) $routingUnit->id))
                                    <div class="flex items-start gap-3 border-b border-gray-100 px-3 py-2 hover:bg-gray-50">
                                        <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                                            <input
                                                type="checkbox"
                                                wire:model.live="linkedRoutingUnitIds"
                                                value="{{ $routingUnit->id }}"
                                                class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            >
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium text-gray-900">{{ $routingUnit->name }}</span>
                                                <span class="block text-xs text-gray-500">
                                                    {{ $routingUnit->type }}
                                                    @if($routingUnit->tierLevel)
                                                        | Tier {{ $routingUnit->tierLevel->level_order }}: {{ $routingUnit->tierLevel->name }}
                                                    @endif
                                                </span>
                                            </span>
                                        </label>
                                        <label class="mt-1 flex shrink-0 items-center gap-1 text-xs font-semibold {{ $isLowestRoutingUnit ? 'cursor-pointer text-gray-700' : 'cursor-not-allowed text-gray-400' }}">
                                            <input
                                                type="radio"
                                                wire:model.live="primaryRoutingUnitId"
                                                value="{{ $routingUnit->id }}"
                                                @disabled(! $isLowestRoutingUnit)
                                                class="border-gray-300 text-blue-600 focus:ring-blue-500 disabled:bg-gray-100"
                                            >
                                            <span>Primary</span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="px-3 py-4 text-sm text-gray-500">
                                        No units match this search.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @error('primaryRoutingUnitId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('linkedRoutingUnitIds') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('linkedRoutingUnitIds.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        <p class="mt-2 text-xs text-gray-500">
                            Select the lowest applicable units here. Use Primary to mark the main route shown for this client space.
                        </p>
                    </div>

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Routes
                        </button>
                        <button type="button" wire:click="$set('showPlacementModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showSecondaryAssignmentModal)
        @php($selectedClientSpace = $clientSpaces->firstWhere('id', $selectedClientSpaceId))
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-lg max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="mb-2 text-lg font-medium leading-6 text-gray-900">
                    Secondary Client Space Assignment
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Use this when the staff member should keep their main location unchanged and is not parked on a last routing node. Last-node staff should be added through Add Staff.
                </p>

                <form wire:submit.prevent="addSecondaryAssignment">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Staff Member</label>
                        <select wire:model="selectedSecondaryStaffAssignmentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Select staff</option>
                            @foreach($secondaryStaffOptions as $assignmentId => $label)
                                <option value="{{ $assignmentId }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('selectedSecondaryStaffAssignmentId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @if($secondaryStaffOptions->isEmpty())
                            <p class="mt-2 text-sm text-gray-500">No staff outside last routing nodes are available for a secondary assignment to this client space.</p>
                        @endif
                    </div>

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-base font-medium text-white hover:bg-gray-800 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm" @disabled($secondaryStaffOptions->isEmpty())>
                            Save Secondary
                        </button>
                        <button type="button" wire:click="$set('showSecondaryAssignmentModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showAddStaffModal)
        @php($selectedClientSpace = $clientSpaces->firstWhere('id', $selectedClientSpaceId))
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-lg max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="mb-2 text-lg font-medium leading-6 text-gray-900">
                    Add Staff to Client Space
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Only staff currently under one of this client space's attached last routing nodes can be added directly here. Staff from those last nodes stay there and are linked to this client space. Everyone else should use Secondary Assignment if their main location should stay unchanged.
                </p>

                <form wire:submit.prevent="addStaffToClientSpace">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Staff Members</label>
                        <div class="mt-1 overflow-hidden rounded-md border border-gray-300">
                            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2">
                                <p class="text-xs text-gray-500">
                                    {{ count($selectedStaffAssignmentIds) }} selected
                                </p>
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="selectAllVisibleStaff" class="text-xs font-semibold text-blue-600 hover:text-blue-500">
                                        Select all
                                    </button>
                                    <button type="button" wire:click="clearStaffSelection" class="text-xs font-semibold text-gray-600 hover:text-gray-500">
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                @forelse($staffOptions as $assignmentId => $label)
                                    <label class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2 hover:bg-gray-50">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedStaffAssignmentIds"
                                            value="{{ $assignmentId }}"
                                            class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <span class="min-w-0 text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                @empty
                                    <div class="px-3 py-4 text-sm text-gray-500">
                                        No eligible staff are currently under this client space's attached last routing nodes.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @error('selectedStaffAssignmentIds') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('selectedStaffAssignmentIds.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-base font-medium text-white hover:bg-gray-800 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm" @disabled($staffOptions->isEmpty() || empty($selectedStaffAssignmentIds))>
                            Add Staff
                        </button>
                        <button type="button" wire:click="$set('showAddStaffModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showShiftPreferenceModal)
        @php($selectedClientSpace = $clientSpaces->firstWhere('id', $selectedClientSpaceId))
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-500 bg-opacity-75 px-4 py-6 sm:items-center">
            <div class="w-full max-w-2xl max-h-[calc(100vh-3rem)] overflow-y-auto rounded-md bg-white px-4 pb-4 pt-5 shadow-xl sm:p-6">
                <h3 class="mb-2 text-lg font-medium leading-6 text-gray-900">
                    Edit Shift Preference
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Update the roster shift preference for this staff member from the client space where they are currently roster-visible.
                </p>

                @if($selectedClientSpace)
                    <p class="mb-4 text-xs font-medium uppercase tracking-wide text-gray-500">
                        Client Space: {{ $selectedClientSpace->name }}
                    </p>
                @endif

                <form wire:submit.prevent="saveShiftPreference" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rostering Mode</label>
                        <select wire:model="shiftPreferenceForm.rostering_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="dynamic">Dynamic</option>
                            <option value="fixed">Fixed</option>
                        </select>
                        @error('shiftPreferenceForm.rostering_mode') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    @if(($shiftPreferenceForm['rostering_mode'] ?? 'dynamic') === 'fixed')
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fixed Shift</label>
                            <select wire:model="shiftPreferenceForm.fixed_shift_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">No fixed shift</option>
                                @foreach($shiftTypeOptions as $shiftTypeId => $shiftTypeLabel)
                                    <option value="{{ $shiftTypeId }}">{{ $shiftTypeLabel }}</option>
                                @endforeach
                            </select>
                            @error('shiftPreferenceForm.fixed_shift_type_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fixed Days</label>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                @foreach($dayOfWeekOptions as $dayValue => $dayLabel)
                                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-2 py-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="shiftPreferenceForm.fixed_days_of_week" value="{{ $dayValue }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span>{{ $dayLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Used when the staff member follows a fixed weekly pattern.</p>
                            @error('shiftPreferenceForm.fixed_days_of_week') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            @error('shiftPreferenceForm.fixed_days_of_week.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Preferred Shifts</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($shiftTypeOptions as $shiftTypeId => $shiftTypeLabel)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-2 py-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="shiftPreferenceForm.preferred_shift_type_ids" value="{{ $shiftTypeId }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>{{ $shiftTypeLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">If no preferred shift is selected, the staff member defaults to Regular working Hours.</p>
                        @error('shiftPreferenceForm.preferred_shift_type_ids') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('shiftPreferenceForm.preferred_shift_type_ids.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Excluded Shifts</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($shiftTypeOptions as $shiftTypeId => $shiftTypeLabel)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-2 py-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="shiftPreferenceForm.excluded_shift_type_ids" value="{{ $shiftTypeId }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>{{ $shiftTypeLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('shiftPreferenceForm.excluded_shift_type_ids') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('shiftPreferenceForm.excluded_shift_type_ids.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rostering Notes</label>
                        <textarea wire:model="shiftPreferenceForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        @error('shiftPreferenceForm.notes') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="shiftPreferenceForm.is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Use This Profile</span>
                    </label>
                    @error('shiftPreferenceForm.is_active') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-900 px-4 py-2 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Shift Preference
                        </button>
                        <button type="button" wire:click="$set('showShiftPreferenceModal', false)" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
