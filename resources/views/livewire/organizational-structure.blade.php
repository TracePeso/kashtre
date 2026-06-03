<div>
    @php($user = Auth::user())

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Routing Structure</h2>
            <p class="mt-1 text-sm text-gray-500">Build the path staff follow through the organization.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if($user?->canSyncHrData())
                <form method="POST" action="{{ route('hr.dashboard.sync') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Sync from KashTre
                    </button>
                </form>
            @endif
            @if($canEditRouting)
                <button wire:click="openModal" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                    Add Root Tier
                </button>
            @endif
        </div>
    </div>

    @unless($canEditRouting)
        <div class="mb-4 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
            You can view the routing structure. Add HR Setup or Edit HR Setup permission is required to change it.
        </div>
    @endunless

    @if (session()->has('message'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('status'))
        <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            {{ session('status') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
            <p class="text-xs font-medium text-gray-500">Tier levels</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $tierLevels->count() }}</p>
        </div>
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
            <p class="text-xs font-medium text-gray-500">Routing nodes</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $structureSummary['routing_nodes'] }}</p>
        </div>
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
            <p class="text-xs font-medium text-gray-500">Root nodes</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $structureSummary['root_nodes'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Top-level paths into the structure</p>
        </div>
        <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
            <p class="text-xs font-medium text-gray-500">Staff pool</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $structureSummary['staff_pool'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Synced staff available for routing</p>
        </div>
    </div>

<div class="mb-5 rounded-md border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-5 py-4">
            <h3 class="text-base font-semibold text-gray-900">Routing Levels</h3>
            <p class="mt-1 text-sm text-gray-500">Define the depth of the structure. Each routing node uses its selected level name automatically.</p>
        </div>

        <div class="grid grid-cols-1 gap-0 lg:grid-cols-[1fr_24rem]">
            <div class="p-5">
                @if($tierLevels->isEmpty())
                    <div class="rounded-md border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">
                        No levels configured yet. Add levels such as Branch, Directorate, Department, Section, Unit, or Team.
                    </div>
                @else
                    <ol class="space-y-2">
                        @foreach($tierLevels as $tierLevel)
                            <li class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-2">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-gray-100 text-xs font-semibold text-gray-700">{{ $tierLevel->level_order }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $tierLevel->name }}</p>
                                        <p class="text-xs text-gray-500">Routing level</p>
                                    </div>
                                </div>
                                @if($canEditRouting)
                                    <div class="ml-3 flex shrink-0 items-center gap-2">
                                        <button type="button" wire:click="openEditTierModal({{ $tierLevel->id }})" class="text-sm font-medium text-gray-600 hover:text-gray-900">Edit</button>
                                        <button type="button" wire:click="deleteTierLevel({{ $tierLevel->id }})" wire:confirm="Delete this tier level?" class="text-sm font-medium text-red-700 hover:text-red-800">Delete</button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @if($canEditRouting)
                <form wire:submit.prevent="saveTierLevel" class="border-t border-gray-200 p-5 lg:border-l lg:border-t-0">
                    <h4 class="text-sm font-semibold text-gray-900">Add Routing Level</h4>
                    <div class="mt-3 grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Order</label>
                            <input type="number" min="1" wire:model="newTierOrder" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('newTierOrder') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700">Level name</label>
                            <input type="text" wire:model="newTierName" placeholder="e.g. Department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('newTierName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Save Level
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-white">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Routing Tree</h3>
                <p class="mt-1 text-sm text-gray-500">Create routing nodes under each level. The selected routing level becomes the node name automatically.</p>
            </div>
            @if($canEditRouting)
                <button wire:click="openModal" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Add Root Tier
                </button>
            @endif
        </div>

        <div class="p-4 sm:p-5">
        @if($rootUnits->isEmpty())
            <div class="rounded-md border border-dashed border-gray-300 px-4 py-8 text-center">
                <p class="text-sm font-medium text-gray-900">No routing nodes yet</p>
                <p class="mt-1 text-sm text-gray-500">Add the first root node in the hierarchy, then add sibling and child nodes under the right levels.</p>
            </div>
        @else
            <ul class="space-y-2">
                @foreach($rootUnits as $unit)
                    @include('livewire.partials.unit-tree', ['unit' => $unit, 'canEditRouting' => $canEditRouting, 'canManageLeafClientSpaceStaff' => $canManageLeafClientSpaceStaff])
                @endforeach
            </ul>
        @endif
        </div>
    </div>

    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-y-auto max-h-[calc(100vh-3rem)] shadow-xl sm:max-w-lg sm:w-full sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                Add {{ $newUnitParentId ? 'Sub-Unit' : 'Root Tier' }}
            </h3>

            <form wire:submit.prevent="saveUnit">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Routing level</label>
                    <select wire:model="newUnitTierLevelId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Select tier level</option>
                        @foreach($tierLevels as $tierLevel)
                            <option value="{{ $tierLevel->id }}">Tier {{ $tierLevel->level_order }}: {{ $tierLevel->name }}</option>
                        @endforeach
                    </select>
                    @error('newUnitTierLevelId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <p class="mt-1 text-xs text-gray-500">The selected routing level becomes the node name.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Parent node</label>
                    <select wire:model="newUnitParentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Root tier</option>
                        @foreach($parentOptions as $parentOption)
                            <option value="{{ $parentOption->id }}">
                                {{ ! $parentOption->parent_id && $parentOption->tierLevel ? $parentOption->tierLevel->name : $parentOption->name }}
                                ({{ $parentOption->tierLevel ? 'Tier '.$parentOption->tierLevel->level_order : $parentOption->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('newUnitParentId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                        Save
                    </button>
                    <button type="button" wire:click="$set('showModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($showEditModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-y-auto max-h-[calc(100vh-3rem)] shadow-xl sm:max-w-lg sm:w-full sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                Configure Routing Node
            </h3>

            <form wire:submit.prevent="saveUnitConfiguration">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Routing level</label>
                    <select wire:model="editUnitTierLevelId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Select tier level</option>
                        @foreach($tierLevels as $tierLevel)
                            <option value="{{ $tierLevel->id }}">Tier {{ $tierLevel->level_order }}: {{ $tierLevel->name }}</option>
                        @endforeach
                    </select>
                    @error('editUnitTierLevelId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <p class="mt-1 text-xs text-gray-500">The selected routing level becomes the node name.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Parent node</label>
                    <select wire:model="editUnitParentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">Root tier</option>
                        @foreach($parentOptions as $parentOption)
                            @continue(in_array($parentOption->id, $blockedParentIds, true))
                            <option value="{{ $parentOption->id }}">
                                {{ ! $parentOption->parent_id && $parentOption->tierLevel ? $parentOption->tierLevel->name : $parentOption->name }}
                                ({{ $parentOption->tierLevel ? 'Tier '.$parentOption->tierLevel->level_order : $parentOption->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('editUnitParentId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Configuration
                    </button>
                    <button type="button" wire:click="$set('showEditModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($showLeafClientSpacesModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-y-auto max-h-[calc(100vh-3rem)] shadow-xl sm:max-w-2xl sm:w-full sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">
                Attach Client Spaces to Last Routing Node
            </h3>
            <p class="mb-4 text-sm text-gray-600">
                {{ $selectedLeafUnit ? $selectedLeafUnit->name : 'This node' }} is the last routing node. Choose the client spaces that should sit under it in the organogram.
            </p>
            @if($autoPromptLeafClientSpaces)
                <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Final node confirmed. Save the client spaces for this node and the staff-to-client-space assignment step will open immediately after.
                </div>
            @endif

            <form wire:submit.prevent="saveLeafClientSpaces">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Client spaces</label>
                    <div class="mt-1 max-h-80 overflow-y-auto rounded-md border border-gray-300">
                        @forelse($clientSpaceOptions as $clientSpace)
                            <label class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2 hover:bg-gray-50">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedLeafClientSpaceIds"
                                    value="{{ $clientSpace->id }}"
                                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="min-w-0 text-sm text-gray-700">
                                    <span class="block font-medium text-gray-900">{{ $clientSpace->name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ $clientSpace->parent ? 'Current primary route: '.$clientSpace->parent->name : 'No primary route yet' }}
                                    </span>
                                </span>
                            </label>
                        @empty
                            <div class="px-3 py-4 text-sm text-gray-500">
                                No client spaces are available yet.
                            </div>
                        @endforelse
                    </div>
                    @error('selectedLeafClientSpaceIds') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    @error('selectedLeafClientSpaceIds.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="mt-5 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Client Spaces
                    </button>
                    <button type="button" wire:click="$set('showLeafClientSpacesModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($showLeafStaffModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-y-auto max-h-[calc(100vh-3rem)] shadow-xl sm:max-w-4xl sm:w-full sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">
                Link Last-Node Staff to Client Spaces
            </h3>
            <p class="mb-4 text-sm text-gray-600">
                {{ $selectedLeafUnit ? $selectedLeafUnit->name : 'This node' }} is the last routing node. Staff stay on this node and can be linked directly to more than one attached client space.
            </p>
            @if($autoPromptLeafStaffAssignments)
                <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    This prompt was opened from the final-node setup flow. Choose a client space first, then add the last-node staff who should be linked to it before you leave this step.
                </div>
            @endif

            @if(! $selectedLeafTargetClientSpaceId)
                <div>
                    <label class="block text-sm font-medium text-gray-700">Client spaces on this node</label>
                    <p class="mt-1 text-xs text-gray-500">
                        Choose a client space to open staff linking for it.
                    </p>
                    <div class="mt-1 max-h-80 overflow-y-auto rounded-md border border-gray-300">
                        @forelse($leafClientSpaceOptions as $clientSpace)
                            <button
                                type="button"
                                wire:click="selectLeafTargetClientSpace({{ $clientSpace->id }})"
                                class="flex w-full items-start justify-between gap-3 border-b border-gray-100 px-3 py-3 text-left hover:bg-gray-50"
                            >
                                <span class="min-w-0 text-sm text-gray-700">
                                    <span class="block font-medium text-gray-900">{{ $clientSpace->name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        @if(($clientSpace->secondary_staff_count ?? 0) > 0)
                                            {{ (int) $clientSpace->secondary_staff_count }} staff linked here
                                        @else
                                            No staff linked here yet
                                        @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-blue-600">
                                    Add Staff
                                </span>
                            </button>
                        @empty
                            <div class="px-3 py-4 text-sm text-gray-500">
                                No client spaces are attached to this last routing node yet.
                            </div>
                        @endforelse
                    </div>
                    @error('selectedLeafTargetClientSpaceId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="mt-5 sm:flex sm:flex-row-reverse">
                    <button type="button" wire:click="$set('showLeafStaffModal', false)" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            @else
                <form wire:submit.prevent="assignLeafStaffToClientSpaces">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Add staff to client space</label>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ optional($leafClientSpaceOptions->firstWhere('id', $selectedLeafTargetClientSpaceId))->name ?? 'Selected client space' }}
                            </p>
                        </div>
                        <button type="button" wire:click="backToLeafClientSpacePicker" class="shrink-0 text-sm font-medium text-blue-600 hover:text-blue-800">
                            Back to Client Spaces
                        </button>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Last-node staff</label>
                        <p class="mt-1 text-xs text-gray-500">
                            These staff remain primary on {{ $selectedLeafUnit?->name ?? 'this last node' }} and are additionally linked to the selected client space.
                        </p>
                        <div class="mt-2 max-h-80 overflow-y-auto rounded-md border border-gray-300">
                            @forelse($leafStaffAssignments as $assignment)
                                @php($linkedSpaceNames = $assignment->clientSpaceStaffAssignments->pluck('clientSpace.name')->filter()->unique()->values())
                                <label class="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2 hover:bg-gray-50">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedLeafStaffAssignmentIds"
                                        value="{{ $assignment->id }}"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span class="min-w-0 text-sm text-gray-700">
                                        <span class="block font-medium text-gray-900">{{ $assignment->staff_name }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $assignment->staff_title ?: 'Title not set' }}
                                            @if($assignment->staff_department)
                                                | {{ $assignment->staff_department }}
                                            @endif
                                        </span>
                                        @if($linkedSpaceNames->isNotEmpty())
                                            <span class="mt-1 block text-xs text-blue-600">
                                                Already linked: {{ $linkedSpaceNames->implode(', ') }}
                                            </span>
                                        @endif
                                    </span>
                                </label>
                            @empty
                                <div class="px-3 py-4 text-sm text-gray-500">
                                    No active staff are currently assigned to this last routing node.
                                </div>
                            @endforelse
                        </div>
                        @error('selectedLeafStaffAssignmentIds') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @error('selectedLeafStaffAssignmentIds.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-5 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm" @disabled(empty($selectedLeafStaffAssignmentIds))>
                            Link Staff
                        </button>
                        <button type="button" wire:click="$set('showLeafStaffModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    @endif

@if($showEditTierModal)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-start justify-center px-4 py-6 sm:items-center">
        <div class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-y-auto max-h-[calc(100vh-3rem)] shadow-xl sm:max-w-lg sm:w-full sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                Edit Routing Level
            </h3>

            <form wire:submit.prevent="updateTierLevel">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Level order</label>
                    <input type="number" min="1" wire:model="editTierOrder" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @error('editTierOrder') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Level name</label>
                    <input type="text" wire:model="editTierName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    @error('editTierName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 sm:ml-3 sm:w-auto sm:text-sm">
                        Update Level
                    </button>
                    <button type="button" wire:click="$set('showEditTierModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
