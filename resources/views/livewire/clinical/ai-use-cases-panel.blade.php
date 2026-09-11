<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Use-Case Governance</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Volume 11 — register, deactivate or prohibit an AI capability for this facility. An inactive or
        prohibited use case is refused the moment a clinician tries it, with the reason named.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <table class="min-w-full text-sm mb-4">
        <thead>
            <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                <th class="pb-1.5">Code</th>
                <th class="pb-1.5">Name</th>
                <th class="pb-1.5">Risk</th>
                <th class="pb-1.5">Status</th>
                <th class="pb-1.5"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr wire:key="uc-{{ $row['code'] }}" class="border-t border-gray-100 dark:border-gray-700">
                    <td class="py-1.5 font-mono text-xs">{{ $row['code'] }}</td>
                    <td class="py-1.5">{{ $row['name'] }}</td>
                    <td class="py-1.5">{{ $row['risk_level'] }}</td>
                    <td class="py-1.5">
                        <span class="text-[10px] px-1.5 py-0.5 rounded uppercase {{ $row['status'] === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">
                            {{ $row['status'] }}
                        </span>
                    </td>
                    <td class="py-1.5 text-right">
                        @if ($row['status'] === 'ACTIVE')
                            <button wire:click="setStatus('{{ $row['code'] }}', 'INACTIVE')" class="text-xs text-red-600 hover:underline">Deactivate</button>
                        @else
                            <button wire:click="setStatus('{{ $row['code'] }}', 'ACTIVE')" class="text-xs text-green-700 hover:underline">Reactivate</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-center text-xs text-gray-400">No tenant-specific overrides — all five use cases fall back to the default registration.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Code</label>
            <input type="text" wire:model="code" placeholder="e.g. ObservationExtraction" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('code') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Name</label>
            <input type="text" wire:model="name" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('name') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Risk Level</label>
            <select wire:model="riskLevel" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="LOW">Low</option>
                <option value="MODERATE">Moderate</option>
                <option value="HIGH">High</option>
                <option value="PROHIBITED">Prohibited</option>
            </select>
        </div>
    </div>
    <button wire:click="register" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Register / Update</button>
</div>
