<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client-Space Assignments</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 1 §4 — who may work in a ward. Live, but the enforcement gate is off by default on Clinical's
        side (<code>REQUIRE_CLIENT_SPACE_ASSIGNMENT=false</code>) — no request is refused for lacking one yet.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="flex items-end gap-2 mb-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Look up user id</label>
            <input type="text" wire:model="lookupUserId" class="w-32 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <button wire:click="lookup" class="text-sm text-white bg-gray-700 hover:bg-gray-800 rounded px-3 py-2">Load</button>
    </div>

    @if ($lookupUserId !== '')
        <table class="min-w-full text-sm mb-5">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-1.5">Client space</th>
                    <th class="pb-1.5">Type</th>
                    <th class="pb-1.5">Start</th>
                    <th class="pb-1.5">End</th>
                    <th class="pb-1.5">Status</th>
                    <th class="pb-1.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="csa-{{ $row['id'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5">{{ $row['client_space_id'] ?? '' }}</td>
                        <td class="py-1.5 text-xs">{{ $types[$row['assignment_type'] ?? ''] ?? ($row['assignment_type'] ?? '') }}</td>
                        <td class="py-1.5 text-xs text-gray-500">{{ $row['effective_start'] ?? '' }}</td>
                        <td class="py-1.5 text-xs text-gray-500">{{ $row['effective_end'] ?? '—' }}</td>
                        <td class="py-1.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ ($row['status'] ?? '') === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $row['status'] ?? 'ACTIVE' }}
                            </span>
                        </td>
                        <td class="py-1.5 text-right">
                            @if (($row['status'] ?? 'ACTIVE') === 'ACTIVE')
                                <button wire:click="end('{{ $row['id'] ?? '' }}')" class="text-[11px] text-red-700 hover:underline">End</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-center text-xs text-gray-400">No assignments for this user.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">User id</label>
            <input type="text" wire:model="userId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('userId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Client space id</label>
            <input type="text" wire:model="clientSpaceId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('clientSpaceId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Assignment type</label>
            <select wire:model="assignmentType" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Effective start</label>
            <input type="datetime-local" wire:model="effectiveStart" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('effectiveStart') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Effective end (optional)</label>
            <input type="datetime-local" wire:model="effectiveEnd" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    </div>
    <button wire:click="assign" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Assign</button>
</div>
