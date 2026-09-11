<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Laboratory Orders</h4>

    <div class="flex gap-2 items-end mb-4">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Test Code</label>
            <input type="text" wire:model="testCode" placeholder="e.g. GLUCOSE, CREATININE, FBC"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('testCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Clinical Indication</label>
            <input type="text" wire:model="clinicalIndication" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <button wire:click="place" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Order
        </button>
    </div>

    @if ($workOrders->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No lab orders yet.</p>
    @else
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-2">Order</th>
                    <th class="pb-2">Status</th>
                    @if ($isStubbed)
                        <th class="pb-2">Simulate LIMS (Dev)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($workOrders as $order)
                    <tr wire:key="labwo-{{ $order->id }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $order->order_type }}</td>
                        <td class="py-1.5">
                            <span class="text-xs px-2 py-0.5 rounded
                                @if ($order->status === 'COMPLETED') bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300
                                @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                {{ $order->status }}
                            </span>
                        </td>
                        @if ($isStubbed)
                            <td class="py-1.5">
                                @if ($order->status !== 'COMPLETED')
                                    <div class="flex gap-1 items-center">
                                        <input type="number" step="any" wire:model="simulatedValues.{{ $order->id }}"
                                            placeholder="Value" class="w-20 text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                        <button wire:click="simulateResult({{ $order->id }})" class="text-xs text-white bg-purple-600 hover:bg-purple-700 rounded px-2 py-1">
                                            Simulate Result
                                        </button>
                                    </div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
