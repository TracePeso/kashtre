<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Encounter Workspace</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 2 — an operational encounter record layered on top of this visit's own <code>visit_id</code>
        (unaffected, still owned by Main). Create → transition status → closure checks → close → reopen.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    @if (! empty($encounters))
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach ($encounters as $e)
                @php $eid = (string) ($e['id'] ?? $e['public_id'] ?? ''); @endphp
                <button wire:click="selectEncounter('{{ $eid }}')" class="text-xs px-2 py-1 rounded border {{ $activeEncounterId === $eid ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-gray-300 dark:border-gray-600' }}">
                    #{{ $eid }} · {{ $e['encounter_class'] ?? '' }} · {{ $e['status'] ?? '' }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($active)
        <div class="border border-gray-200 dark:border-gray-700 rounded p-4 mb-5">
            <div class="text-xs font-medium mb-3">
                Encounter #{{ $active['id'] ?? '' }} — {{ $active['encounter_class'] ?? '' }} —
                <span class="uppercase">{{ $active['status'] ?? '' }}</span>
                @if ($active['minimum_data_pending'] ?? false)
                    <span class="text-amber-600">· minimum data pending</span>
                @endif
            </div>

            <div class="flex flex-wrap items-end gap-2 mb-3">
                <div>
                    <label class="block text-[10px] font-medium text-gray-500 mb-1">New status</label>
                    <input type="text" wire:model="newStatus" placeholder="e.g. ARRIVED" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                </div>
                <div class="flex-1 min-w-[8rem]">
                    <label class="block text-[10px] font-medium text-gray-500 mb-1">Reason (optional)</label>
                    <input type="text" wire:model="statusReason" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                </div>
                <button wire:click="transition" class="text-xs text-white bg-blue-600 hover:bg-blue-700 rounded px-3 py-1.5">Transition</button>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-3">
                <button wire:click="checkClosure" class="text-xs text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5">Check closure readiness</button>
                <button wire:click="close" class="text-xs text-white bg-red-600 hover:bg-red-700 rounded px-3 py-1.5">Close</button>
                @if ($closureChecks && ! ($closureChecks['ready'] ?? true))
                    <button wire:click="close(true)" wire:confirm="Override outstanding closure items and close anyway?" class="text-xs text-white bg-amber-600 hover:bg-amber-700 rounded px-3 py-1.5">Close (override)</button>
                @endif
            </div>

            @if ($closureChecks)
                <div class="text-xs mb-3 {{ $closureChecks['ready'] ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $closureChecks['ready'] ? 'Ready to close.' : 'Not ready — outstanding items:' }}
                    @unless ($closureChecks['ready'])
                        <ul class="list-disc list-inside mt-1">
                            @foreach ($closureChecks['items'] as $key => $value)
                                @if ($value)
                                    <li>{{ str_replace('_', ' ', $key) }}: {{ $value }}</li>
                                @endif
                            @endforeach
                        </ul>
                    @endunless
                </div>
            @endif

            <div class="border-t border-gray-100 dark:border-gray-700 pt-3 flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-medium text-gray-500 mb-1">Reopen reason</label>
                    <input type="text" wire:model="reopenReason" class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                    @error('reopenReason') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                </div>
                <button wire:click="reopen" class="text-xs text-white bg-gray-700 hover:bg-gray-800 rounded px-3 py-1.5">Reopen</button>
            </div>
        </div>
    @endif

    <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Encounter class</label>
                <select wire:model="encounterClass" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    @foreach ($classes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Service</label>
                <input type="text" wire:model="service" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Facility id</label>
                <input type="text" wire:model="facilityId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            </div>
        </div>
        <button wire:click="create" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Start new encounter</button>
    </div>
</div>
