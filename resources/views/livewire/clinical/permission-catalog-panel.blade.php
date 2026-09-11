<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Permission Catalogue</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 1 §6 — the published atomic-code catalogue ({{ $meta['count'] ?? 163 }} codes). Clinical
        publishes what a code means; Main registers and assigns it to users/title bundles on its own side.
        <strong>Most of these codes aren't enforced anywhere yet</strong> — don't assume a 403 for "wrong
        permission" on ordinary chart actions today.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2 mb-4">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search…" class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        <select wire:model.live="riskTierFilter" class="text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            <option value="">Any risk tier</option>
            <option value="LOW">Low</option>
            <option value="MODERATE">Moderate</option>
            <option value="HIGH">High</option>
            <option value="CRITICAL">Critical</option>
        </select>
    </div>

    <div class="max-h-96 overflow-y-auto mb-5">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-1.5">Code</th>
                    <th class="pb-1.5">Risk</th>
                    <th class="pb-1.5">Scope</th>
                    <th class="pb-1.5">Status</th>
                    <th class="pb-1.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="pc-{{ $row['code'] ?? $loop->index }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5 font-mono text-xs">{{ $row['code'] ?? '' }}</td>
                        <td class="py-1.5 text-xs">{{ $row['risk_tier'] ?? '' }}</td>
                        <td class="py-1.5 text-xs">{{ $row['default_scope'] ?? '' }}</td>
                        <td class="py-1.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ ($row['status'] ?? '') === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $row['status'] ?? '' }}
                            </span>
                        </td>
                        <td class="py-1.5 text-right">
                            @if (($row['status'] ?? '') === 'ACTIVE')
                                <button wire:click="deactivate('{{ $row['id'] ?? $row['code'] ?? '' }}')" wire:confirm="Retire this code? It stays resolvable for historical audit but can never be newly assigned again." class="text-[11px] text-red-700 hover:underline">Retire</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-center text-xs text-gray-400">No codes match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Code</label>
            <input type="text" wire:model="newCode" placeholder="clinical.example.approve" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('newCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Description</label>
            <input type="text" wire:model="newDescription" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('newDescription') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Risk tier</label>
            <select wire:model="newRiskTier" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="LOW">Low</option>
                <option value="MODERATE">Moderate</option>
                <option value="HIGH">High</option>
                <option value="CRITICAL">Critical</option>
            </select>
        </div>
    </div>
    <button wire:click="register" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Register code</button>
</div>
