<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maternity — Birth Event</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Recorded on the mother's chart.</p>

    @if ($statusMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    @if (! empty($birthEvents))
        <div class="space-y-2 mb-4">
            @foreach ($birthEvents as $event)
                <div wire:key="birth-{{ $event['id'] }}" class="text-xs border border-gray-100 dark:border-gray-700 rounded p-2">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $event['delivery_at'] ?? '' }}</span>
                        <span class="text-gray-400">{{ $event['delivery_mode_code'] ?? '' }}</span>
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                        {{ $event['infant_count'] ?? count($event['records'] ?? []) }} infant(s)
                        @if ($event['gestation_weeks'] ?? null)
                            &middot; {{ $event['gestation_weeks'] }} weeks
                        @endif
                        @if ($event['maternal_outcome_code'] ?? null)
                            &middot; {{ $event['maternal_outcome_code'] }}
                        @endif
                    </div>
                    @foreach ($event['records'] ?? [] as $record)
                        <div wire:key="record-{{ $record['id'] }}" class="mt-1 pl-2 border-l-2 border-gray-100 dark:border-gray-700 text-gray-600 dark:text-gray-300">
                            Infant {{ $record['birth_order'] ?? '' }}: {{ $record['sex'] ?? '' }}, {{ $record['birth_outcome'] ?? '' }}
                            @if ($record['birth_weight_value'] ?? null)
                                &middot; {{ $record['birth_weight_value'] }}g
                            @endif
                            &middot; registration {{ $record['registration_status'] ?? 'unknown' }}
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Delivered At</label>
            <input type="datetime-local" wire:model="deliveryAt" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @error('deliveryAt') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Delivery Mode</label>
            <select wire:model="deliveryModeCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Select&hellip;</option>
                @foreach (($options['DELIVERY_MODE'] ?? []) as $opt)
                    <option value="{{ $opt['code'] }}">{{ $opt['display_label'] }}</option>
                @endforeach
            </select>
            @error('deliveryModeCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Presentation</label>
            <select wire:model="presentationCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Optional&hellip;</option>
                @foreach (($options['PRESENTATION'] ?? []) as $opt)
                    <option value="{{ $opt['code'] }}">{{ $opt['display_label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Maternal Outcome</label>
            <select wire:model="maternalOutcomeCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Optional&hellip;</option>
                @foreach (($options['MATERNAL_OUTCOME'] ?? []) as $opt)
                    <option value="{{ $opt['code'] }}">{{ $opt['display_label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Gestation (weeks)</label>
            <input type="number" step="0.1" wire:model="gestationWeeks" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Infant Sex</label>
            {{-- Values must match Clinical's own infants.*.sex enum exactly
                 (MaternityController::store()) — "AMBIGUOUS" isn't one of
                 them and was silently rejecting every submission that picked
                 it, surfaced only as a generic "The given data was invalid." --}}
            <select wire:model="infantSex" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="FEMALE">Female</option>
                <option value="MALE">Male</option>
                <option value="INDETERMINATE">Indeterminate</option>
                <option value="UNKNOWN">Unknown</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Birth Outcome</label>
            <select wire:model="infantOutcome" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="LIVE_BIRTH">Live Birth</option>
                <option value="STILLBIRTH">Stillbirth</option>
                <option value="NEONATAL_DEATH">Neonatal Death</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Birth Weight (g)</label>
            <input type="number" wire:model="infantWeight" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
        </div>
    </div>
    <div class="mt-2">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Delivery Notes</label>
        <input type="text" wire:model="deliveryNotes" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
    </div>
    <button wire:click="recordBirth" class="mt-3 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
        Record Birth Event
    </button>
</div>
