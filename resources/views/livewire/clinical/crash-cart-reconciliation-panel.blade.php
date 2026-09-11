<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Emergency Crash Cart Reconciliation</h4>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        For items used from a crash cart during a resuscitation — one submission charts every item against this
        patient, decrements that cart's stock, and bills the account. For an ordinary item taken from ward stock,
        use Point-of-Care Consumption below instead.
    </p>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ $resultMessage }}</div>
    @endif

    @if ($errorMessage)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded p-3">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Resuscitation Event Id</label>
            <input type="text" wire:model="resuscitationEventId"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('resuscitationEventId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Crash Cart</label>
            <select wire:model="crashCartStoreId" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                <option value="">Select&hellip;</option>
                @foreach ($crashCarts as $cart)
                    <option value="{{ $cart->id }}">{{ $cart->name }} ({{ ucfirst($cart->crash_cart_status ?? '—') }})</option>
                @endforeach
            </select>
            @error('crashCartStoreId') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
            @if ($crashCarts->isEmpty())
                <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1">No store is marked as a crash cart yet — set one up under Inventory → Crash Carts.</p>
            @endif
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Narrative (optional)</label>
            <input type="text" wire:model="narrative" placeholder="Brief account of the event"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mb-4">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                    <th class="px-3 py-2">Item Used</th>
                    <th class="px-3 py-2 w-24">Qty</th>
                    <th class="px-3 py-2 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($lines as $index => $line)
                    <tr wire:key="line-{{ $index }}">
                        <td class="px-3 py-1.5 text-gray-900 dark:text-gray-100">{{ $line['display_name'] ?? $line['inventory_sku'] }}</td>
                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ $line['quantity_used'] }}</td>
                        <td class="px-3 py-1.5 text-right">
                            <button type="button" wire:click="removeLine({{ $index }})" class="text-xs text-red-600 hover:underline">Remove</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-4 text-center text-xs text-gray-400">No items added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex items-end gap-2 mb-4">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Item</label>
            <input type="text" wire:model="newItemCode" list="crash-cart-items" placeholder="Search items&hellip;"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            <datalist id="crash-cart-items">
                @foreach ($stockedItems as $item)
                    <option value="{{ $item->name }}"></option>
                @endforeach
            </datalist>
            @error('newItemCode') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div class="w-24">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Qty Used</label>
            <input type="number" step="any" wire:model="newItemQty"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('newItemQty') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <button wire:click="addLine" class="text-sm text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded px-3 py-2">
            Add Item
        </button>
    </div>

    <button wire:click="reconcile" class="text-sm text-white bg-red-600 hover:bg-red-700 rounded px-4 py-2">
        Reconcile Crash Cart Usage
    </button>
</div>
