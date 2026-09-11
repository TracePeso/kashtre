<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delegation &amp; Cross-Cover</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 1 §10 — explicit, time-limited, scoped, auditable delegated authority. A delegator can't
        delegate authority they don't hold, and delegated authority can't outlive the stated expiry.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="flex items-end gap-2 mb-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Look up delegator user id</label>
            <input type="text" wire:model="lookupUserId" class="w-40 text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <button wire:click="lookup" class="text-sm text-white bg-gray-700 hover:bg-gray-800 rounded px-3 py-2">Load</button>
    </div>

    @if ($lookupUserId !== '')
        <table class="min-w-full text-sm mb-5">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-1.5">Delegate</th>
                    <th class="pb-1.5">Bundle</th>
                    <th class="pb-1.5">Scope</th>
                    <th class="pb-1.5">Window</th>
                    <th class="pb-1.5">Status</th>
                    <th class="pb-1.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="del-{{ $row['id'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5">{{ $row['delegate_user_id'] ?? '' }}</td>
                        <td class="py-1.5 text-xs">{{ $row['permission_bundle'] ?? '' }}</td>
                        <td class="py-1.5 text-xs">{{ $row['scope'] ?? '' }}</td>
                        <td class="py-1.5 text-xs text-gray-500">{{ $row['start'] ?? '' }} &rarr; {{ $row['end'] ?? '' }}</td>
                        <td class="py-1.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ ($row['status'] ?? 'ACTIVE') === 'REVOKED' ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' }}">
                                {{ $row['status'] ?? 'ACTIVE' }}
                            </span>
                        </td>
                        <td class="py-1.5 text-right">
                            @if (($row['status'] ?? 'ACTIVE') !== 'REVOKED')
                                <button wire:click="revoke('{{ $row['id'] ?? '' }}')" class="text-[11px] text-red-700 hover:underline">Revoke</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-center text-xs text-gray-400">No delegations from this user.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason (for revoke above)</label>
            <input type="text" wire:model="revokeReason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Delegator user id</label>
            <input type="text" wire:model="delegatorUserId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('delegatorUserId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Delegate user id</label>
            <input type="text" wire:model="delegateUserId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('delegateUserId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Permission bundle</label>
            <input type="text" wire:model="permissionBundle" placeholder="e.g. MEDICAL_OFFICER_GENERAL" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('permissionBundle') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Scope</label>
            <select wire:model="scope" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($scopes as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Client space id (optional)</label>
            <input type="text" wire:model="clientSpaceId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Start</label>
            <input type="datetime-local" wire:model="start" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('start') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">End</label>
            <input type="datetime-local" wire:model="end" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('end') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason</label>
            <input type="text" wire:model="reason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('reason') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>
    <button wire:click="delegate" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Delegate</button>
</div>
