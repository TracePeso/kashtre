<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Medication Adverse Events</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 6 — adverse drug reactions, side effects, medication errors and near-misses. Reports something
        that already happened, not a decision about the current chart.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="space-y-3 mb-5">
        @forelse ($events as $event)
            <div wire:key="ae-{{ $event['id'] ?? $event['public_id'] ?? $loop->index }}" class="border border-gray-200 dark:border-gray-700 rounded p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium">{{ $eventTypes[$event['event_type'] ?? ''] ?? ($event['event_type'] ?? '') }} — {{ $event['severity'] ?? '' }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ ($event['status'] ?? '') === 'OPEN' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : (($event['status'] ?? '') === 'UNDER_REVIEW' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300') }}">
                        {{ $event['status'] ?? '' }}{{ ($event['escalated'] ?? false) ? ' · escalated' : '' }}
                    </span>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">{{ $event['description'] ?? '' }}</p>

                @if (($event['status'] ?? null) !== 'CLOSED')
                    @php $eid = (string) ($event['id'] ?? $event['public_id'] ?? ''); @endphp
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="stepInput.{{ $eid }}" placeholder="Response / outcome text…" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 flex-1 min-w-[10rem]" />
                        <button wire:click="recordResponse('{{ $eid }}')" class="text-xs text-blue-700 hover:underline">Record response</button>
                        @if (! ($event['escalated'] ?? false))
                            <button wire:click="escalate('{{ $eid }}')" class="text-xs text-amber-700 hover:underline">Escalate</button>
                        @endif
                        <button wire:click="close('{{ $eid }}')" wire:confirm="Close this event with the outcome entered above?" class="text-xs text-green-700 hover:underline">Close</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-xs text-gray-400">No adverse events reported for this patient.</p>
        @endforelse
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Event type</label>
            <select wire:model="eventType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($eventTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Severity</label>
            <select wire:model="severity" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="MILD">Mild</option>
                <option value="MODERATE">Moderate</option>
                <option value="SEVERE">Severe</option>
                <option value="LIFE_THREATENING">Life-threatening</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Description</label>
            <input type="text" wire:model="description" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('description') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>
    <button wire:click="report" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Report event</button>
</div>
