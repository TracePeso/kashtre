<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sensitivity Restrictions</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 1 §12 — ordinary title, permission and client-space assignment do not automatically override a
        restriction here.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="space-y-2 mb-5">
        @forelse ($rows as $row)
            <div wire:key="sr-{{ $row['id'] ?? $loop->index }}" class="flex items-center justify-between text-xs border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded p-2">
                <span class="text-amber-800 dark:text-amber-300">
                    🔒 {{ $levels[$row['level'] ?? ''] ?? ($row['level'] ?? '') }} restricted — {{ $row['label'] ?? '' }}
                </span>
                @if (empty($row['lifted_at']))
                    <button wire:click="lift('{{ $row['id'] ?? '' }}')" class="text-amber-700 hover:underline">Lift</button>
                @else
                    <span class="text-gray-400">Lifted</span>
                @endif
            </div>
        @empty
            <p class="text-xs text-gray-400">No sensitivity restrictions on this patient's record.</p>
        @endforelse
    </div>

    @if (! empty($rows))
        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason (for lifting above)</label>
            <input type="text" wire:model="liftReason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Level</label>
            <select wire:model="level" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($levels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Label</label>
            <input type="text" wire:model="label" placeholder="e.g. VIP, staff-member, mental health" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('label') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason</label>
            <input type="text" wire:model="reason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('reason') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>
    <button wire:click="restrict" class="text-sm text-white bg-amber-600 hover:bg-amber-700 rounded px-4 py-2">Apply restriction</button>
</div>
