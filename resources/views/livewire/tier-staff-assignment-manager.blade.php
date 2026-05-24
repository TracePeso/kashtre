<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Tier Pool</h2>
            <p class="mt-1 text-sm text-gray-500">Manage routing nodes by tier level and route staff through the right node.</p>
        </div>
        <a href="{{ route('hr.organizational-structure.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Routing Structure
        </a>
    </div>

    @if($message)
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ $message }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if($tierUnits->isEmpty())
        <div class="rounded-md border border-dashed border-gray-300 bg-white px-5 py-8 text-center">
            <p class="text-sm font-medium text-gray-900">No routing nodes yet.</p>
            <p class="mt-1 text-sm text-gray-500">Create tier levels and routing nodes in Routing Structure first.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[22rem_1fr]">
            <div class="rounded-md border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Routing Nodes</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose the routing node you want to manage.</p>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($tierRows as $tierRow)
                        @continue(! $tierRow['is_visible'])
                        @php($unit = $tierRow['unit'])
                        @php($unitDisplayName = ! $unit->parent_id && $unit->tierLevel ? $unit->tierLevel->name : $unit->name)
                        @php($isSelected = $selectedTierId === $unit->id)
                        @php($indent = 20 + $tierRow['depth'] * 16)
                        <div class="relative flex items-stretch transition {{ $isSelected ? 'bg-gray-900' : 'hover:bg-gray-50' }}">
                            <button
                                type="button"
                                wire:click="selectTier({{ $unit->id }})"
                                class="min-w-0 flex-1 py-3 text-left"
                                style="padding-left: {{ $indent }}px; padding-right: {{ $tierRow['has_children'] ? '2.5rem' : '1.25rem' }};"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-gray-900' }}">{{ $unitDisplayName }}</p>
                                    <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold {{ $isSelected ? 'bg-white text-gray-900' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $tierRow['staff_count'] }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs {{ $isSelected ? 'text-gray-300' : 'text-gray-500' }}">
                                    @if($unit->tierLevel)
                                        Tier {{ $unit->tierLevel->level_order }}{{ $unit->parent_id ? ': '.$unit->tierLevel->name : '' }}
                                    @else
                                        {{ $unit->type }}
                                    @endif
                                    @if($tierRow['depth'] === 0 && $tierRow['subtier_count'] > 0)
                                        &middot; {{ $tierRow['subtier_count'] }} direct node{{ $tierRow['subtier_count'] === 1 ? '' : 's' }}
                                    @endif
                                </p>
                            </button>
                            @if($tierRow['has_children'])
                                <button
                                    type="button"
                                    wire:click="toggleTierExpansion({{ $unit->id }})"
                                    class="absolute right-0 top-0 flex h-full w-9 items-center justify-center {{ $isSelected ? 'text-gray-400 hover:text-white' : 'text-gray-400 hover:text-gray-700' }}"
                                    title="{{ $tierRow['is_expanded'] ? 'Collapse' : 'Expand' }}"
                                >
                                    @if($tierRow['is_expanded'])
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                        </svg>
                                    @endif
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white">
                @if($selectedTier)
                    @php($selectedTierName = ! $selectedTier->parent_id && $selectedTier->tierLevel ? $selectedTier->tierLevel->name : $selectedTier->name)
                    @php($selectedParentName = $selectedTier->parent ? (! $selectedTier->parent->parent_id && $selectedTier->parent->tierLevel ? $selectedTier->parent->tierLevel->name : $selectedTier->parent->name) : null)
                    <div class="border-b border-gray-200 px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ $selectedTierName }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $selectedParentName ? 'Parent node: '.$selectedParentName : 'Root routing node' }}
                                </p>
                            </div>
                            <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                {{ $directSubtiers->count() }} direct node{{ $directSubtiers->count() === 1 ? '' : 's' }} / {{ $assignedStaff->count() }} staff
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="mb-5 rounded-md border border-gray-200 bg-white p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">Direct Routing Nodes</h4>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Staff assigned to {{ $selectedTierName }} can route unrouted staff into these directly linked nodes.
                                    </p>
                                </div>
                                @if($canManageSubtiers)
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">Manage nodes</span>
                                @endif
                            </div>

                            @if($canManageSubtiers)
                                @if($subtierLevelOptions->isEmpty())
                                    <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        Create a lower routing level in Routing Structure before adding a direct node under {{ $selectedTierName }}.
                                    </div>
                                @else
                                    <form wire:submit.prevent="createSubtier" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                                        <select wire:model="newSubtierTierLevelId" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            @foreach($subtierLevelOptions as $tierLevel)
                                                <option value="{{ $tierLevel->id }}">Tier {{ $tierLevel->level_order }}: {{ $tierLevel->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                            Add Routing Node
                                        </button>
                                    </form>
                                    <p class="mt-2 text-xs text-gray-500">The selected routing level becomes the direct node name.</p>
                                    @error('newSubtierTierLevelId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                @endif
                            @else
                                <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    Only users with Add HR Setup/Edit HR Setup or staff assigned to {{ $selectedTierName }} can add direct nodes here.
                                </div>
                            @endif

                            @if($directSubtiers->isEmpty())
                                <div class="mt-3 rounded-md border border-dashed border-gray-300 px-4 py-6 text-center">
                                    <p class="text-sm text-gray-500">No direct routing nodes under this node yet.</p>
                                </div>
                            @else
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach($directSubtiers as $subtier)
                                        <button type="button" wire:click="selectTier({{ $subtier->id }})" class="rounded-md border border-gray-200 px-4 py-3 text-left hover:border-gray-400 hover:bg-gray-50">
                                            <p class="text-sm font-semibold text-gray-900">{{ $subtier->name }}</p>
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $subtier->tierLevel ? 'Tier '.$subtier->tierLevel->level_order.': '.$subtier->tierLevel->name : $subtier->type }}
                                            </p>
                                            <p class="mt-2 text-xs text-gray-500">
                                                {{ $subtier->assigned_staff_count }} staff assigned
                                            </p>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if($selectedTier && ! $selectedTier->parent && $routesToSubtier)
                            @if($canSeedTier)
                                <form wire:submit.prevent="assignRoutingStaff" class="mb-5 rounded-md border border-sky-200 bg-sky-50 p-4">
                                    <h4 class="text-sm font-semibold text-sky-950">
                                        Add Routing Staff to This Root Node
                                    </h4>
                                    <p class="mt-1 text-xs text-sky-800">
                                        These staff stay in {{ $selectedTierName }} and can route other staff into its direct nodes.
                                    </p>
                                    <p class="mt-2 text-xs font-medium text-sky-900">
                                        {{ $routingStaffSeedEligibility['eligible_count'] }} of {{ $routingStaffSeedEligibility['total'] }} unrouted staff eligible for this route.
                                    </p>
                                    @if(! empty($routingStaffSeedEligibility['blocked_reasons']))
                                        <div class="mt-2 rounded-md border border-sky-200 bg-white px-3 py-2 text-xs text-sky-900">
                                            <p class="font-semibold">Not shown:</p>
                                            <ul class="mt-1 space-y-1">
                                                @foreach(array_slice($routingStaffSeedEligibility['blocked_reasons'], 0, 3, true) as $reason => $count)
                                                    <li>{{ $count }} staff: {{ $reason }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        <select wire:model="routingStaffUuid" class="block w-full rounded-md border-sky-300 shadow-sm focus:border-sky-600 focus:ring-sky-600 sm:text-sm">
                                            <option value="">Select staff member</option>
                                            @foreach($tierRoutingStaffOptions as $uuid => $name)
                                                <option value="{{ $uuid }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-sky-900 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-50" @disabled(empty($tierRoutingStaffOptions))>
                                            Add Routing Staff
                                        </button>
                                    </div>
                                    @error('routingStaffUuid') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if(empty($tierRoutingStaffOptions))
                                        <p class="mt-2 text-sm text-sky-800">
                                            No eligible unassigned staff found for this root node.
                                        </p>
                                    @endif
                                </form>
                            @else
                                <div class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    Add HR Staff or Edit HR Staff permission is required to seed routing staff into this root node.
                                </div>
                            @endif
                        @endif

                        @if($canAssignStaff)
                            <form wire:submit.prevent="assignStaff" class="mb-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                                <h4 class="text-sm font-semibold text-gray-900">
                                    {{ $routesToSubtier ? 'Route Staff to Direct Node' : 'Add Staff to This Node' }}
                                </h4>
                                <p class="mt-1 text-xs text-gray-500">
                                    @if($routesToSubtier)
                                        {{ $selectedTierName }} has direct routing nodes, so staff are routed to one of those nodes instead of staying here.
                                    @else
                                        This node has no direct routing nodes yet, so staff are added directly here and can route onward when linked nodes are created.
                                    @endif
                                </p>
                                <p class="mt-2 text-xs font-medium text-gray-700">
                                    {{ $staffRouteEligibility['eligible_count'] }} of {{ $staffRouteEligibility['total'] }} unrouted staff eligible for this route.
                                </p>
                                @if(! empty($staffRouteEligibility['blocked_reasons']))
                                    <div class="mt-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700">
                                        <p class="font-semibold">Not shown:</p>
                                        <ul class="mt-1 space-y-1">
                                            @foreach(array_slice($staffRouteEligibility['blocked_reasons'], 0, 3, true) as $reason => $count)
                                                <li>{{ $count }} staff: {{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="mt-3 grid grid-cols-1 gap-2 {{ $routesToSubtier ? 'sm:grid-cols-[14rem_1fr_auto]' : 'sm:grid-cols-[1fr_auto]' }}">
                                    @if($routesToSubtier)
                                        <select wire:model="staffTargetTierId" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            @foreach($directSubtiers as $subtier)
                                                <option value="{{ $subtier->id }}">{{ $subtier->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <select wire:model="staffUuid" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="">Select staff member</option>
                                        @foreach($staffOptions as $uuid => $name)
                                            <option value="{{ $uuid }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50" @disabled(empty($staffOptions))>
                                        {{ $routesToSubtier ? 'Route to Node' : 'Add Staff' }}
                                    </button>
                                </div>
                                @error('staffTargetTierId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('staffUuid') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                @if(empty($staffOptions))
                                    <p class="mt-2 text-sm text-gray-500">No eligible unrouted staff found for this route.</p>
                                @endif
                            </form>
                        @else
                            <div class="mb-5 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                @if($routesToSubtier)
                                    Only users with Add HR Staff/Edit HR Staff or staff assigned to {{ $selectedTierName }} can route staff to its direct nodes.
                                @elseif($selectedTier->parent)
                                    Only users with Add HR Staff/Edit HR Staff or staff assigned to {{ $selectedParentName }} can manage this node.
                                @else
                                    Add HR Staff or Edit HR Staff permission is required to add staff to a root node.
                                @endif
                            </div>
                        @endif

                        <div>
                            <h4 class="text-sm font-semibold uppercase text-gray-500">
                                {{ $routesToSubtier ? 'Staff Currently In This Node' : 'Assigned Staff' }}
                            </h4>
                            @if($assignedStaff->isEmpty())
                                <div class="mt-3 rounded-md border border-dashed border-gray-300 px-4 py-6 text-center">
                                    <p class="text-sm text-gray-500">
                                        {{ $routesToSubtier ? 'No staff are currently waiting in this node; new staff will be routed to a direct node.' : 'No staff assigned to this node yet.' }}
                                    </p>
                                </div>
                            @else
                                <div class="mt-3 divide-y divide-gray-100 rounded-md border border-gray-200">
                                    @foreach($assignedStaff as $assignment)
                                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900">{{ $assignment->staff_name }}</p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    {{ $assignment->staff_cadre ?: 'Cadre not set' }}
                                                    @if($assignment->staff_department)
                                                        <span class="mx-1">/</span>
                                                        {{ $assignment->staff_department }}
                                                    @endif
                                                    @if($assignment->staff_title)
                                                        <span class="mx-1">/</span>
                                                        {{ $assignment->staff_title }}
                                                    @endif
                                                    <span class="mx-1">/</span>
                                                    {{ str_replace('_', ' ', $assignment->status) }}
                                                </p>
                                            </div>
                                            @if($canRemoveStaff)
                                                <button type="button" wire:click="unassignStaff({{ $assignment->id }})" class="text-sm font-medium text-red-700 hover:text-red-800">
                                                    Remove
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-900">Select a routing node</p>
                        <p class="mt-1 text-sm text-gray-500">Choose a node from the left to manage its staff pool.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
