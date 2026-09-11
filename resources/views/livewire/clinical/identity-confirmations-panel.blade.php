<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Positive Patient Identification</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 2 — generalizes MAR's 5-Rights check to 7 more action types beyond medication administration.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="space-y-1 mb-5 max-h-48 overflow-y-auto">
        @forelse ($rows as $row)
            <div wire:key="idc-{{ $row['id'] ?? $loop->index }}" class="text-xs border-t border-gray-100 dark:border-gray-700 py-1.5">
                <span class="font-medium">{{ $row['action_type'] ?? '' }}</span>
                <span class="text-gray-400"> — {{ $row['method'] ?? '' }} — {{ $row['created_at'] ?? '' }}</span>
            </div>
        @empty
            <p class="text-xs text-gray-400">No identity confirmations recorded yet.</p>
        @endforelse
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Action type</label>
            <select wire:model="actionType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($actionTypes as $type)
                    <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Method</label>
            <select wire:model="method" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="VERBAL_AND_BAND">Verbal + wristband</option>
                <option value="BAND_SCAN">Wristband scan</option>
                <option value="VERBAL_ONLY">Verbal only</option>
                <option value="PROXY_CONFIRMED">Confirmed by proxy/family</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Notes (optional)</label>
            <input type="text" wire:model="notes" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    </div>
    <button wire:click="confirm" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Confirm identity</button>
</div>
