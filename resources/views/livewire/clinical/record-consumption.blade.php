<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Point-of-Care Consumption</h4>

    @if ($lastResultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $lastResultMessage }}</div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Item</label>
            <select wire:model="itemCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Select&hellip;</option>
                @foreach ($items as $item)
                    <option value="{{ $item->code }}">{{ $item->name }}</option>
                @endforeach
            </select>
            @error('itemCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Qty</label>
            <input type="number" step="any" wire:model="quantity" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('quantity') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fact</label>
            <select wire:model="factToken" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="MEDICATION_ADMINISTERED">Administered</option>
                <option value="MEDICATION_WASTED">Wasted</option>
                <option value="NON_APPROVED_FLOOR_STOCK_USAGE">Non-Approved Floor Stock</option>
                <option value="CRASH_CART_CONSUMPTION">Crash Cart</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Context</label>
            <select wire:model="usageContext" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="PATIENT">Patient</option>
                <option value="ADMINISTRATIVE">Administrative</option>
                <option value="CRASH_CART">Crash Cart</option>
                <option value="WASTAGE_OPERATIONAL">Wastage (Operational)</option>
                <option value="WASTAGE_EXPIRED">Wastage (Expired)</option>
            </select>
        </div>
    </div>

    <button wire:click="record" class="mt-3 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
        Record
    </button>

    @if ($recentEvents->isNotEmpty())
        <table class="min-w-full text-sm mt-4">
            <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="pb-2">Item</th>
                    <th class="pb-2">Qty</th>
                    <th class="pb-2">Scenario</th>
                    <th class="pb-2">Billing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentEvents as $event)
                    <tr wire:key="ce-{{ $event->id }}" class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $event->item_code }}</td>
                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $event->quantity }}</td>
                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $event->reconciliation_scenario }}</td>
                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $event->billing_triggered ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
