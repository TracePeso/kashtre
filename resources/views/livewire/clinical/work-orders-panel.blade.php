<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ward Tasks</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Ad-hoc ward tasks only — a lab or imaging request goes through Orders instead.</p>

    @if ($statusMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $statusMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    @if (empty($workOrders))
        <p class="text-xs text-gray-400 mb-4">No tasks raised yet.</p>
    @else
        <table class="min-w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-1.5">Task</th>
                    <th class="pb-1.5">Status</th>
                    <th class="pb-1.5">Due</th>
                    <th class="pb-1.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workOrders as $wo)
                    <tr wire:key="wo-{{ $wo['id'] }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5 text-gray-900 dark:text-gray-100">
                            {{ $wo['order_name'] ?? $wo['order_type'] ?? '' }}
                            @if (($wo['order_type'] ?? null) === 'CLINICAL_FOLLOWUP')
                                {{-- Raised by a discharge/transfer (v6.1 Volume 8) — same row shape, just worth calling out. --}}
                                <span class="text-[10px] px-1.5 py-0.5 rounded uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 ml-1">Follow-up</span>
                            @endif
                        </td>
                        <td class="py-1.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase
                                {{ $wo['status'] === 'COMPLETED' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                {{ $wo['status'] }}
                            </span>
                        </td>
                        <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $wo['due_at'] ?? $wo['created_at'] ?? '' }}</td>
                        <td class="py-1.5 text-right">
                            @if (! in_array($wo['status'], ['COMPLETED', 'CANCELLED'], true))
                                <button wire:click="transition('{{ $wo['id'] }}', 'COMPLETED')" class="text-xs text-blue-700 dark:text-blue-300 hover:underline mr-2">Complete</button>
                                <button wire:click="transition('{{ $wo['id'] }}', 'CANCELLED')" class="text-xs text-gray-400 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="flex gap-2 items-end">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Task</label>
            <input type="text" wire:model="orderName" placeholder="e.g. Re-site cannula" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
            @error('orderName') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Notes</label>
            <input type="text" wire:model="notes" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
        </div>
        <button wire:click="create" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Raise Task</button>
    </div>
</div>
