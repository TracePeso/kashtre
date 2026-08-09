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

    @forelse ($wards as $ward)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $ward->ward_name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ward->building_wing }} &middot; {{ $ward->ward_code }}</p>
                </div>
                <button wire:click="addOverflowBed({{ $ward->id }})"
                    class="text-sm px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    + Add Overflow Bed
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                @foreach ($ward->beds as $bed)
                    <div wire:key="bed-{{ $bed->id }}"
                        class="border rounded-lg p-3
                            @if ($bed->operational_state === 'OCCUPIED') border-blue-300 bg-blue-50 dark:bg-blue-900/30 dark:border-blue-700
                            @elseif ($bed->operational_state === 'RESERVED') border-amber-300 bg-amber-50 dark:bg-amber-900/30 dark:border-amber-700
                            @else border-green-300 bg-green-50 dark:bg-green-900/30 dark:border-green-700 @endif">

                        <div class="flex items-center justify-between">
                            <span class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $bed->bed_code }}</span>
                            @if ($bed->is_overflow)
                                <span class="text-[10px] uppercase tracking-wide text-orange-600 dark:text-orange-400">Overflow</span>
                            @endif
                        </div>

                        @if ($bed->operational_state === 'OCCUPIED')
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $bed->current_client_id }}</div>
                            <div class="mt-2 flex gap-2">
                                <a href="{{ route('clinical.observations.show', $bed->current_client_id) }}"
                                    class="text-xs text-blue-700 dark:text-blue-300 hover:underline">Open Chart</a>
                                <button wire:click="releaseBed({{ $bed->id }})"
                                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Release</button>
                            </div>
                        @elseif ($occupyingBedId === $bed->id)
                            <div class="mt-2 space-y-1" wire:key="occupy-form-{{ $bed->id }}">
                                <input type="text" wire:model="occupyClientId" placeholder="Client ID"
                                    class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                <input type="text" wire:model="occupyVisitId" placeholder="Visit ID (optional)"
                                    class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                @error('occupyClientId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                <div class="flex gap-2">
                                    <button wire:click="confirmOccupy" class="text-xs text-white bg-blue-600 rounded px-2 py-1">Confirm</button>
                                    <button wire:click="cancelOccupy" class="text-xs text-gray-500">Cancel</button>
                                </div>
                            </div>
                        @else
                            <button wire:click="startOccupy({{ $bed->id }})"
                                class="mt-2 text-xs text-green-700 dark:text-green-300 hover:underline">Occupy</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
            No wards configured for this business yet.
        </div>
    @endforelse
</div>
