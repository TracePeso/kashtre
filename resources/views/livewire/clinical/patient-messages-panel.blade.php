<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Patient Messages</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        v6.1 Volume 13 — secure messaging. No SMS/push provider is configured yet, so delivery status may show
        FAILED/ESCALATED — the message itself is still charted either way.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">{{ $errorMessage }}</div>
    @endif

    <ul class="text-sm mb-4 space-y-1 max-h-48 overflow-y-auto">
        @forelse ($messages as $m)
            <li wire:key="msg-{{ $m['public_id'] }}" class="border-t border-gray-100 dark:border-gray-700 pt-1">
                <span class="text-[10px] px-1.5 py-0.5 rounded uppercase bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ $m['status'] ?? '' }}</span>
                {{ $m['body'] ?? '' }}
            </li>
        @empty
            <li class="text-xs text-gray-400">No messages yet.</li>
        @endforelse
    </ul>

    <div class="mb-2">
        <textarea wire:model="body" rows="2" placeholder="Message to the patient…" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600"></textarea>
        @error('body') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
    </div>
    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300 mb-3">
        <input type="checkbox" wire:model="isUrgent" /> Urgent
    </label>
    <button wire:click="send" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Send</button>
</div>
