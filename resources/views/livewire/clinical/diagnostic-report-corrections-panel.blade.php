<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Diagnostic Report Corrections</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Phase 8 — correct, mark entered-in-error, or cancel a diagnostic report already on this patient's
        chart. Enter the report id from the result you're correcting; a correction never rewrites the original,
        it preserves it (amended) and issues a new version. Each of these three is terminal — a report already
        corrected, entered-in-error or cancelled cannot be changed again.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Report id</label>
            <input type="text" wire:model="reportId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('reportId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Action</label>
            <select wire:model="action" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="correct">Correct</option>
                <option value="entered-in-error">Mark entered-in-error</option>
                <option value="cancel">Cancel</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason</label>
            <input type="text" wire:model="reason" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('reason') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>
    <button wire:click="submit" wire:confirm="This action is terminal for this report. Continue?" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Apply</button>
</div>
