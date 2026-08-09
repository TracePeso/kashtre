<div class="space-y-6">

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Patient {{ $clientId }}</h3>
                @if ($visitId)
                    <p class="text-sm text-gray-500 dark:text-gray-400">Visit {{ $visitId }}</p>
                @endif
            </div>

            @unless ($hasActiveRelationship)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-amber-600 dark:text-amber-400">No active care assignment for you on this patient.</span>
                    @if (in_array('Act As Ward Nurse (Clinical)', auth()->user()->permissions ?? []))
                        <button wire:click="claim('nurse')" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600">Claim as Nurse</button>
                    @endif
                    @if (in_array('Act As Consultant (Clinical)', auth()->user()->permissions ?? []))
                        <button wire:click="claim('doctor')" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600">Claim as Doctor</button>
                    @endif
                </div>
            @endunless
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Record Observations</h4>

        @if (count($captureErrors))
            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
                <ul class="list-disc list-inside">
                    @foreach ($captureErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($cdes as $cde)
                <div wire:key="cde-{{ $cde->cde_code }}">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">{{ $cde->cde_name }}</label>
                    <div class="flex gap-1">
                        <input type="number" step="any" wire:model="values.{{ $cde->cde_code }}"
                            class="flex-1 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        @if ($unitOptionsByCde[$cde->cde_code]->count() > 1)
                            <select wire:model="inputUnits.{{ $cde->cde_code }}"
                                class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                @foreach ($unitOptionsByCde[$cde->cde_code] as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_label }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="self-center text-xs text-gray-500 dark:text-gray-400 w-14">{{ $unitOptionsByCde[$cde->cde_code]->first()?->unit_label }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <button wire:click="save" class="mt-4 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Save Observations
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Flowsheet</h4>

        @if ($recentObservations->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No observations recorded yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <th class="pb-2">CDE</th>
                        <th class="pb-2">Value</th>
                        <th class="pb-2">Base Value</th>
                        <th class="pb-2">Captured At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentObservations as $observation)
                        <tr wire:key="obs-{{ $observation->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $observation->cde_code }}</td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $observation->captured_value_numeric }}</td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $observation->base_value_numeric }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $observation->captured_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
