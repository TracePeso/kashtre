<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Medication Reconciliation</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 6 — what the patient was actually taking, decided item by item against the current chart, at
        admission, transfer or discharge. Separate from the eMAR order/administration pipeline below.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    @if (! $active)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reconciliation type</label>
                <select wire:model="reconciliationType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="start" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Start reconciliation</button>
        </div>
    @else
        <div class="border border-gray-200 dark:border-gray-700 rounded p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium">Status: <span class="uppercase">{{ $active['status'] ?? '—' }}</span></span>
                @if (($active['status'] ?? null) !== 'COMPLETED')
                    <button wire:click="complete" wire:confirm="Mark this reconciliation complete? Every item must have a decision." class="text-xs text-white bg-green-600 hover:bg-green-700 rounded px-3 py-1.5">Complete reconciliation</button>
                @endif
            </div>

            <table class="min-w-full text-sm mb-4">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                        <th class="pb-1.5">Source</th>
                        <th class="pb-1.5">Medication</th>
                        <th class="pb-1.5">Dose</th>
                        <th class="pb-1.5">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($active['items'] ?? []) as $item)
                        <tr wire:key="mr-item-{{ $item['id'] ?? $item['public_id'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-xs">{{ $item['source'] ?? '' }}</td>
                            <td class="py-1.5">{{ $item['medication_name'] ?? '' }}</td>
                            <td class="py-1.5 text-xs">{{ $item['dose'] ?? '—' }}</td>
                            <td class="py-1.5">
                                @if (empty($item['decision']))
                                    <select wire:change="decideItem('{{ $item['id'] ?? $item['public_id'] }}', $event.target.value)" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                        <option value="">Choose…</option>
                                        @foreach ($decisions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-[10px] px-1.5 py-0.5 rounded uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">{{ $item['decision'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-center text-xs text-gray-400">No items yet — add the patient's source medication list below.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Source</label>
                    <select wire:model="newItemSource" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                        <option value="PATIENT_REPORT">Patient report</option>
                        <option value="FAMILY_REPORT">Family report</option>
                        <option value="PHARMACY_RECORD">Pharmacy record</option>
                        <option value="PRIOR_DISCHARGE_SUMMARY">Prior discharge summary</option>
                        <option value="REFERRING_FACILITY">Referring facility</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Medication name</label>
                    <input type="text" wire:model="newItemMedicationName" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                    @error('newItemMedicationName') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Dose</label>
                    <input type="text" wire:model="newItemDose" placeholder="e.g. 5mg" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                </div>
            </div>
            <button wire:click="addItem" class="mt-3 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Add item</button>
        </div>
    @endif
</div>
