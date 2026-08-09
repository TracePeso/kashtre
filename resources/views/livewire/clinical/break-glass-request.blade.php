<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 max-w-lg mx-auto">
    <h3 class="text-lg font-medium text-red-700 dark:text-red-400 mb-2">Break Glass — Emergency Access</h3>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
        You do not have an active care relationship with this patient. Requesting access here is logged and
        audited (user, patient, reason, and a time-boxed grant).
    </p>

    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Reason</label>
    <select wire:model="reasonCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 mb-3">
        <option value="">Select&hellip;</option>
        @foreach ($reasons as $reason)
            <option value="{{ $reason->reason_code }}">{{ $reason->display_label }}</option>
        @endforeach
    </select>
    @error('reasonCode') <div class="text-[10px] text-red-600 mb-2">{{ $message }}</div> @enderror

    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Justification Note</label>
    <textarea wire:model="justificationNote" rows="3" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 mb-3"></textarea>
    @error('justificationNote') <div class="text-[10px] text-red-600 mb-2">{{ $message }}</div> @enderror

    <button wire:click="grant" class="text-sm text-white bg-red-600 hover:bg-red-700 rounded px-4 py-2">
        Grant Emergency Access (15 min)
    </button>
</div>
