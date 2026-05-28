<li class="rounded-md border border-gray-200 bg-white">
    @php($leafCandidate = ! $unit->hasRoutingChildren() && ! $unit->isLowestRoutingNode())
    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-gray-900">{{ ! $unit->parent_id && $unit->tierLevel ? $unit->tierLevel->name : $unit->name }}</span>
                <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
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

            <p class="mt-1 text-xs text-gray-500">
                Staff assigned to this tier can route to its child tiers.
            </p>
            @if($unit->isLowestRoutingNode())
                <p class="mt-1 text-xs text-blue-600">
                    This is the last routing node. Attach client spaces here, then link last-node staff directly to one or more attached client spaces.
                </p>
            @elseif($leafCandidate)
                <p class="mt-1 text-xs text-amber-600">
                    If this should be the last routing node, click Last Node? first. After that, child tiers will be locked and the client-space flow will open immediately.
                </p>
            @endif
            <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                <span class="rounded bg-gray-50 px-2 py-1">
                    {{ $unit->routing_staff_count ?? $unit->staffAssignments()->whereNotIn('status', ['inactive', 'orphaned'])->count() }} routing staff
                </span>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            @if($canEditRouting)
                <button wire:click="openEditModal({{ $unit->id }})" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    Edit
                </button>
                @if($leafCandidate)
                    <button wire:click="openLeafClientSpacesModal({{ $unit->id }})" class="text-sm font-medium text-amber-600 hover:text-amber-800">
                        Last Node?
                    </button>
                @endif
                @if($unit->isLowestRoutingNode())
                    <button wire:click="openLeafClientSpacesModal({{ $unit->id }})" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">
                        Attach Client Spaces
                    </button>
                @endif
                @if(($canManageLeafClientSpaceStaff ?? false) && $unit->isLowestRoutingNode())
                    <button wire:click="openLeafStaffModal({{ $unit->id }})" class="text-sm font-medium text-sky-600 hover:text-sky-800">
                        Assign Staff to Spaces
                    </button>
                @endif
                @if(! $unit->isLowestRoutingNode())
                    <button wire:click="openModal({{ $unit->id }})" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                        Add Child
                    </button>
                @endif
            @endif
        </div>
    </div>

    @php($routingChildren = $unit->children->filter(fn ($c) => $c->isRoutingNode()))
    @if($routingChildren->isNotEmpty())
        <ul class="ml-4 space-y-2 border-l border-gray-200 pb-4 pl-4">
            @foreach($routingChildren as $child)
                @include('livewire.partials.unit-tree', ['unit' => $child, 'canEditRouting' => $canEditRouting, 'canManageLeafClientSpaceStaff' => $canManageLeafClientSpaceStaff ?? false])
            @endforeach
        </ul>
    @endif
</li>
