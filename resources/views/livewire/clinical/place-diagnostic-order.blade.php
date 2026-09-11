<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Diagnostic Orders (Imaging)</h4>

    <div class="flex gap-2 items-end mb-4">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Protocol</label>
            <select wire:model="protocolCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Select a protocol&hellip;</option>
                @foreach ($protocols as $protocol)
                    <option value="{{ $protocol->code }}">{{ $protocol->name }} ({{ $protocol->modality_type }})</option>
                @endforeach
            </select>
            @error('protocolCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
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
        <p class="text-sm text-gray-500 dark:text-gray-400">No diagnostic orders yet.</p>
    @else
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-2">Order</th>
                    <th class="pb-2">Status</th>
                    <th class="pb-2">Placed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($workOrders as $order)
                    <tr wire:key="wo-{{ $order->id }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $order->order_type }}</td>
                        <td class="py-1.5">
                            <span class="text-xs px-2 py-0.5 rounded
                                @if ($order->status === 'COMPLETED') bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300
                                @elseif ($order->status === 'IN_PROGRESS') bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                                @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif">
                                {{ $order->status }}
                            </span>
                        </td>
                        <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $order->created_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
