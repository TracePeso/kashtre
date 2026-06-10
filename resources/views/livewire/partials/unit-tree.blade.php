<li wire:key="routing-node-{{ $unit->id }}" class="rounded-md border border-gray-200 bg-white shadow-sm">
    @php($leafCandidate = ! $unit->hasRoutingChildren() && ! $unit->isLowestRoutingNode())
    <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-semibold text-gray-900">{{ ! $unit->parent_id && $unit->tierLevel ? $unit->tierLevel->name : $unit->name }}</span>
                <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-700">
                    @if($unit->tierLevel)
                        Tier {{ $unit->tierLevel->level_order }}
                        @if($unit->parent_id)
                            : {{ $unit->tierLevel->name }}
                        @endif
                    @else
                        {{ $unit->type }}
                    @endif
                </span>
            </div>

            <p class="mt-1 text-[11px] text-gray-500">
                Staff assigned to this tier can route to its child tiers.
            </p>
            @if($unit->isLowestRoutingNode())
                <p class="mt-1 text-[11px] text-blue-600">
                    Last routing node.
                </p>
                @php($attachedClientSpaces = $unit->relationLoaded('linkedClientSpaces') ? $unit->linkedClientSpaces : collect())
                @php($attachedClientSpaceCount = $unit->linked_client_spaces_count ?? $attachedClientSpaces->count())
                @if($attachedClientSpaceCount > 0)
                    <p class="mt-1 text-[11px] text-emerald-600">
                        {{ $attachedClientSpaceCount }} client space{{ $attachedClientSpaceCount === 1 ? '' : 's' }} attached.
                    </p>
                    <p class="mt-1 text-[11px] text-gray-500">
                        {{ $attachedClientSpaces->pluck('name')->filter()->implode(', ') }}
                    </p>
                @else
                    <p class="mt-1 text-[11px] text-amber-600">
                        No client spaces attached yet.
                    </p>
                @endif
            @elseif($leafCandidate)
                <p class="mt-1 text-[11px] text-amber-600">
                    Click Last Node? to lock child tiers.
                </p>
            @endif
            <div class="mt-1.5 flex flex-wrap gap-1.5 text-[11px] text-gray-500">
                <span class="rounded bg-gray-50 px-1.5 py-0.5">
                    {{ $unit->routing_staff_count ?? $unit->staffAssignments()->whereNotIn('status', ['inactive', 'orphaned'])->count() }} routing staff
                </span>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2 text-[13px]">
            @if($canEditRouting)
                <button type="button" x-on:click="openInstantModal('edit', {{ $unit->id }})" wire:click="openEditModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openEditModal({{ $unit->id }})" class="text-sm font-medium text-gray-600 hover:text-gray-900 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="openEditModal({{ $unit->id }})">Edit</span>
                    <span wire:loading wire:target="openEditModal({{ $unit->id }})">Opening...</span>
                </button>
                @if($leafCandidate)
                    <button type="button" x-on:click="openInstantModal('leaf-client-spaces', {{ $unit->id }})" wire:click="openLeafClientSpacesModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openLeafClientSpacesModal({{ $unit->id }})" class="text-sm font-medium text-amber-600 hover:text-amber-800 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="openLeafClientSpacesModal({{ $unit->id }})">Last Node?</span>
                        <span wire:loading wire:target="openLeafClientSpacesModal({{ $unit->id }})">Opening...</span>
                    </button>
                @endif
                @if($unit->isLowestRoutingNode())
                    <button type="button" x-on:click="openInstantModal('leaf-client-spaces', {{ $unit->id }})" wire:click="openLeafClientSpacesModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openLeafClientSpacesModal({{ $unit->id }})" class="text-sm font-medium text-emerald-600 hover:text-emerald-800 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="openLeafClientSpacesModal({{ $unit->id }})">Attach Client Spaces</span>
                        <span wire:loading wire:target="openLeafClientSpacesModal({{ $unit->id }})">Opening...</span>
                    </button>
                @endif
                @if(($canManageLeafClientSpaceStaff ?? false) && $unit->isLowestRoutingNode())
                    <button type="button" x-on:click="openInstantModal('leaf-staff', {{ $unit->id }})" wire:click="openLeafStaffModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openLeafStaffModal({{ $unit->id }})" class="text-sm font-medium text-sky-600 hover:text-sky-800 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="openLeafStaffModal({{ $unit->id }})">Assign Staff to Spaces</span>
                        <span wire:loading wire:target="openLeafStaffModal({{ $unit->id }})">Opening...</span>
                    </button>
                @endif
                @if(($canManageRoutingNodeStaffTools ?? false))
                    <button type="button" x-on:click="openInstantModal('routing-node-staff', {{ $unit->id }})" wire:click="openRoutingNodeStaffModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openRoutingNodeStaffModal({{ $unit->id }})" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="openRoutingNodeStaffModal({{ $unit->id }})">Manage Staff</span>
                        <span wire:loading wire:target="openRoutingNodeStaffModal({{ $unit->id }})">Opening...</span>
                    </button>
                @endif
                @if(! $unit->isLowestRoutingNode())
                    <button type="button" wire:click="openModal({{ $unit->id }})" wire:loading.attr="disabled" wire:target="openModal({{ $unit->id }})" class="text-sm font-medium text-blue-600 hover:text-blue-800 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="openModal({{ $unit->id }})">Add Child</span>
                        <span wire:loading wire:target="openModal({{ $unit->id }})">Opening...</span>
                    </button>
                @endif
            @endif
        </div>
    </div>

    @php($routingChildren = $unit->children->filter(fn ($c) => $c->isRoutingNode()))
    @if($routingChildren->isNotEmpty())
        <ul class="ml-3 space-y-2 border-l border-gray-200 pb-3 pl-3 sm:ml-4 sm:pl-4">
            @foreach($routingChildren as $child)
                @include('livewire.partials.unit-tree', ['unit' => $child, 'canEditRouting' => $canEditRouting, 'canManageLeafClientSpaceStaff' => $canManageLeafClientSpaceStaff ?? false, 'canManageRoutingNodeStaffTools' => $canManageRoutingNodeStaffTools ?? false])
            @endforeach
        </ul>
    @endif
</li>
