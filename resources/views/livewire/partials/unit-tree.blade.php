<li class="rounded-md border border-gray-200 bg-white">
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
                Staff assigned to this tier can route to its child tiers and client spaces.
            </p>
            @if($unit->isLowestRoutingNode())
                <p class="mt-1 text-xs text-blue-600">
                    This is the last routing node. Attach client spaces here, then place staff directly into the correct client space.
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
                @if($unit->isLowestRoutingNode())
                    <button wire:click="openLeafClientSpacesModal({{ $unit->id }})" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">
                        Attach Client Spaces
                    </button>
                @endif
                <button wire:click="openModal({{ $unit->id }})" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    Add Child
                </button>
            @endif
        </div>
    </div>

    @php($routingChildren = $unit->children->filter(fn ($c) => $c->isRoutingNode()))
    @php($linkedClientSpaces = $unit->relationLoaded('linkedClientSpaces')
        ? $unit->linkedClientSpaces->unique('id')->values()
        : $unit->linkedClientSpaces()->withCount([
            'staffAssignments as active_staff_count' => fn ($query) => $query->where('status', 'active'),
            'secondaryStaffAssignments as secondary_staff_count',
        ])->get()->unique('id')->values())

    @if($linkedClientSpaces->isNotEmpty())
        <ul class="ml-4 space-y-2 border-l border-gray-200 pb-4 pl-4">
            @foreach($linkedClientSpaces as $clientSpace)
                <li class="rounded-md border border-sky-200 bg-sky-50">
                    <div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900">{{ $clientSpace->name }}</span>
                                <span class="inline-flex items-center rounded bg-white px-2 py-0.5 text-xs font-medium text-sky-700">
                                    Client Space
                                </span>
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $clientSpace->pivot?->is_primary ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $clientSpace->pivot?->is_primary ? 'Primary Route' : 'Shared Route' }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ (int) $clientSpace->active_staff_count }} primary staff
                                @if(($clientSpace->secondary_staff_count ?? 0) > 0)
                                    | {{ (int) $clientSpace->secondary_staff_count }} additional staff
                                @endif
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if($routingChildren->isNotEmpty())
        <ul class="ml-4 space-y-2 border-l border-gray-200 pb-4 pl-4">
            @foreach($routingChildren as $child)
                @include('livewire.partials.unit-tree', ['unit' => $child, 'canEditRouting' => $canEditRouting])
            @endforeach
        </ul>
    @endif
</li>
