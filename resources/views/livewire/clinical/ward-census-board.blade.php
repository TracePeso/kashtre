<div class="space-y-6">

    {{-- SRD §5.2: Real-Time Ward Census Header Widget --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Beds</div>
            <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $census['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Occupied</div>
            <div class="text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $census['occupied'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Reserved</div>
            <div class="text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ $census['reserved'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Available</div>
            <div class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ $census['available'] }}</div>
        </div>
    </div>

    @if ($actionError)
        {{-- The owning side writes these for a clinician; show them verbatim. --}}
        <div class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/30 dark:border-red-700 p-4 text-sm text-red-800 dark:text-red-200">
            {{ $actionError }}
        </div>
    @endif

    @if ($actionMessage)
        <div class="rounded-lg border border-blue-300 bg-blue-50 dark:bg-blue-900/30 dark:border-blue-700 p-4 text-sm text-blue-900 dark:text-blue-200">
            <div>{{ $actionMessage }}</div>
            @if (! empty($skippedBeds))
                <ul class="mt-2 list-disc pl-5 space-y-0.5">
                    @foreach ($skippedBeds as $skipped)
                        <li>
                            <span class="font-mono">{{ $skipped['bed_code'] ?? $skipped['bed_id'] ?? '' }}</span>
                            — {{ $skipped['reason'] ?? 'Left in place.' }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @forelse ($wards as $ward)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
            <div class="flex items-start justify-between mb-4 gap-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $ward->ward_name ?: $ward->ward_code }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if ($ward->building_wing){{ $ward->building_wing }} &middot; @endif{{ $ward->ward_code }}
                        @if ($ward->overflow > 0)
                            &middot; <span class="text-orange-600 dark:text-orange-400">{{ $ward->overflow }} surge</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 justify-end">
                    @if ($ward->spaceIdForOverflow())
                        <button wire:click="addOverflowBed({{ $ward->spaceIdForOverflow() }})"
                            class="text-sm px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            + Add Overflow Bed
                        </button>
                    @endif

                    {{-- Only meaningful while surge capacity is actually standing. --}}
                    @if ($ward->hasVacantOverflowBeds())
                        <button wire:click="clearSurgeBeds('{{ $ward->ward_code }}')"
                            class="text-sm px-3 py-1.5 rounded-md border border-orange-300 dark:border-orange-700 text-orange-700 dark:text-orange-300 hover:bg-orange-50 dark:hover:bg-orange-900/30">
                            Clear Surge Beds
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                @foreach ($ward->beds as $bed)
                    <div wire:key="bed-{{ $bed->id }}"
                        class="border rounded-lg p-3
                            @if ($bed->isOccupied()) border-blue-300 bg-blue-50 dark:bg-blue-900/30 dark:border-blue-700
                            @elseif ($bed->isReserved()) border-amber-300 bg-amber-50 dark:bg-amber-900/30 dark:border-amber-700
                            @else border-green-300 bg-green-50 dark:bg-green-900/30 dark:border-green-700 @endif">

                        <div class="flex items-center justify-between">
                            <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $bed->bed_code }}</span>
                            @if ($bed->is_overflow)
                                <span class="text-[10px] uppercase tracking-wide text-orange-600 dark:text-orange-400">Overflow</span>
                            @endif
                        </div>

                        @if ($bed->room_number)
                            <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $bed->room_number }}</div>
                        @endif

                        @if ($bed->isOccupied() || $bed->isReserved())
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                {{ $bed->current_client_id }}
                                @if ($bed->isReserved())
                                    <span class="text-amber-700 dark:text-amber-300">(held)</span>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($bed->isOccupied() && $bed->current_client_id)
                                    {{-- Carry the visit: claiming the patient and
                                         every patient-scoped clinical call require
                                         it, and the bed is where we know it. --}}
                                    <a href="{{ route('clinical.observations.show', ['clientId' => $bed->current_client_id, 'visit_id' => $bed->current_visit_id]) }}"
                                        class="text-xs text-blue-700 dark:text-blue-300 hover:underline">Open Chart</a>
                                @endif
                                @if ($bed->isReserved())
                                    <button wire:click="startAssign({{ $bed->id }})"
                                        class="text-xs text-blue-700 dark:text-blue-300 hover:underline">Assign</button>
                                @endif
                                <button wire:click="releaseBed({{ $bed->id }})"
                                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Release</button>
                            </div>
                        @elseif ($actioningBedId === $bed->id)
                            <div class="mt-2 space-y-1" wire:key="form-{{ $bed->id }}">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $bedAction === 'reserve' ? 'Reserve for' : 'Assign to' }}
                                </div>
                                <input type="text" wire:model="patientId" placeholder="Patient ID"
                                    class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                <input type="text" wire:model="visitId" placeholder="Visit ID"
                                    class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                @error('patientId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                @error('visitId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                <div class="flex gap-2">
                                    <button wire:click="confirmBedAction" class="text-xs text-white bg-blue-600 rounded px-2 py-1">Confirm</button>
                                    <button wire:click="cancelAction" class="text-xs text-gray-500">Cancel</button>
                                </div>
                            </div>
                        @else
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button wire:click="startAssign({{ $bed->id }})"
                                    class="text-xs text-green-700 dark:text-green-300 hover:underline">Assign</button>
                                <button wire:click="startReserve({{ $bed->id }})"
                                    class="text-xs text-amber-700 dark:text-amber-300 hover:underline">Reserve</button>
                                @if ($bed->is_overflow)
                                    <button wire:click="retireBed({{ $bed->id }})"
                                        class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Retire</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            No wards returned. Ward structure is administrative — create client spaces and baseline
            beds in settings before the census board has anything to show.
        </div>
    @endforelse
</div>
