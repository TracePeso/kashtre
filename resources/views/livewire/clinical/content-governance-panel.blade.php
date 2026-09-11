<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Content Governance</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Volume 14 — versioned, validated clinical content (protocols, order sets, educational material).
        Promotion to a live environment is not available yet — no shipped action creates the package it needs.
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
                <th class="pb-1.5">Type</th>
                <th class="pb-1.5">Version</th>
                <th class="pb-1.5">Status</th>
                <th class="pb-1.5"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr wire:key="cv-{{ $row['public_id'] }}" class="border-t border-gray-100 dark:border-gray-700">
                    <td class="py-1.5">{{ $row['content_type'] ?? $row['definition_public_id'] ?? '' }}</td>
                    <td class="py-1.5">v{{ $row['version_no'] ?? '' }}</td>
                    <td class="py-1.5">
                        <span class="text-[10px] px-1.5 py-0.5 rounded uppercase bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $row['status'] ?? '' }}</span>
                    </td>
                    <td class="py-1.5 text-right">
                        <button wire:click="validateVersion('{{ $row['public_id'] }}')" class="text-xs text-blue-700 dark:text-blue-300 hover:underline">Validate</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-center text-xs text-gray-400">No content definitions yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Content Type</label>
            <input type="text" wire:model="contentType" placeholder="e.g. PROTOCOL" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('contentType') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Content Code</label>
            <input type="text" wire:model="contentCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('contentCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Version No</label>
            <input type="number" wire:model="versionNo" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    </div>
    <div class="mb-3">
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Payload (JSON)</label>
        <textarea wire:model="payloadJson" rows="3" class="w-full text-sm font-mono rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
        @error('payloadJson') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
    </div>
    <button wire:click="createVersion" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Create Version</button>
</div>
