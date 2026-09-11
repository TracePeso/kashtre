<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Identity Concerns</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 2 — report only. This flags a concern for someone else to investigate; it never merges or
        corrects the patient record itself.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="space-y-2 mb-5">
        @forelse ($rows as $row)
            <div wire:key="idcn-{{ $row['id'] ?? $loop->index }}" class="border border-gray-200 dark:border-gray-700 rounded p-2">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="font-medium">{{ str_replace('_', ' ', $row['concern_type'] ?? '') }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ ($row['status'] ?? 'OPEN') === 'OPEN' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $row['status'] ?? 'OPEN' }}
                    </span>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">{{ $row['description'] ?? '' }}</p>
                @if (($row['status'] ?? 'OPEN') === 'OPEN')
                    @php $cid = (string) ($row['id'] ?? ''); @endphp
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="resolutionInput.{{ $cid }}" placeholder="Resolution…" class="flex-1 text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                        <button wire:click="resolve('{{ $cid }}')" class="text-xs text-green-700 hover:underline">Resolve</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-xs text-gray-400">No identity concerns reported for this patient.</p>
        @endforelse
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Concern type</label>
            <select wire:model="concernType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($concernTypes as $type)
                    <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Description</label>
            <input type="text" wire:model="description" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('description') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>
    <button wire:click="report" class="text-sm text-white bg-amber-600 hover:bg-amber-700 rounded px-4 py-2">Report concern</button>
</div>
